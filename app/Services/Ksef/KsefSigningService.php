<?php

declare(strict_types=1);

namespace App\Services\Ksef;

/**
 * Tworzy XML AuthTokenRequest dla KSeF i podpisuje go w formacie XAdES-BES.
 *
 * KSeF wymaga (per CIRFMF/ksef-docs/uwierzytelnianie.md):
 *   - SHA-256 jako digest dla document i SignedProperties
 *   - Inclusive C14N dla canonicalization (NIE exclusive — to częsty trap;
 *     potwierdzone w Billu-System komentarzami)
 *   - ECDSA: signature jako IEEE P1363 (raw r||s), nie DER
 *   - Schema namespace: http://ksef.mf.gov.pl/auth/token/2.0
 *
 * Adapted from Billu-System KsefCertificateService::signXml.
 */
class KsefSigningService
{
    private const NS_AUTH = 'http://ksef.mf.gov.pl/auth/token/2.0';

    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    private const C14N_INCLUSIVE = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

    private const DIGEST_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    private const SIG_ECDSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256';

    private const SIG_RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    /**
     * Buduje surowy AuthTokenRequest XML — bez podpisu.
     *
     * Opcjonalnie embeduje `<EncryptionKey>` blok z wrapped AES-256 kluczem
     * (RSA-OAEP wrap przez MF public key). Bez tego cert-based session
     * init NIE wystarcza do wysyłki faktur — invoice/Send wymaga AES
     * encryption tym samym kluczem, który MF zna z embedded key w
     * podpisanym AuthTokenRequest.
     *
     * Caller (zwykle `KsefClient::authenticate`):
     *   1. Generuje ephemeral AES-256 (random_bytes(32))
     *   2. Wrap'uje przez RSA-OAEP z MF public key
     *   3. Przekazuje base64 wrapped key tutaj
     *   4. Trzyma raw AES key w cache razem z sessionToken żeby móc
     *      potem wywołać `KsefHttpClient::sendInvoice(token, aesKey, xml)`
     *
     * Patrz KSeF spec §3.2 (https://www.podatki.gov.pl/ksef/specyfikacja-techniczna/).
     */
    public function buildAuthTokenRequest(
        string $challenge,
        string $contextNip,
        string $identifierType = 'certificateSubject',
        ?string $wrappedAesKeyBase64 = null,
    ): string {
        $encryptionKeyBlock = '';
        if ($wrappedAesKeyBase64 !== null && $wrappedAesKeyBase64 !== '') {
            $encryptionKeyBlock = '<EncryptionKey>'
                .htmlspecialchars($wrappedAesKeyBase64, ENT_XML1)
                .'</EncryptionKey>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<AuthTokenRequest xmlns="'.self::NS_AUTH.'"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<Challenge>'.htmlspecialchars($challenge, ENT_XML1).'</Challenge>'
            .'<ContextIdentifier><Nip>'.htmlspecialchars($contextNip, ENT_XML1).'</Nip></ContextIdentifier>'
            .'<SubjectIdentifierType>'.htmlspecialchars($identifierType, ENT_XML1).'</SubjectIdentifierType>'
            .$encryptionKeyBlock
            .'</AuthTokenRequest>';

        return $xml;
    }

    /**
     * Podpisuje XML AuthTokenRequest XAdES-BES.
     *
     * Dwa tryby:
     *   1. PFX-based (qualified cert / pieczęć): pass $pfxData + $password
     *   2. PEM-based (KSeF-issued): pass $keyPem + $certPem (przekazujemy
     *      cert jako "password" gdy $isPem=true)
     */
    public function signAuthTokenRequest(
        string $xml,
        string $pfxOrKeyPem,
        string $passwordOrCertPem,
        bool $isPem = false,
    ): string {
        // ─── 1. Załaduj klucz prywatny + cert publiczny ─────────────
        if ($isPem) {
            $privateKey = openssl_pkey_get_private($pfxOrKeyPem);
            if (! $privateKey) {
                throw new \RuntimeException('Nie można odczytać klucza prywatnego (PEM): '.openssl_error_string());
            }
            $certPem = $passwordOrCertPem;
        } else {
            $certs = [];
            if (! openssl_pkcs12_read($pfxOrKeyPem, $certs, $passwordOrCertPem)) {
                throw new \RuntimeException('Nie można odczytać PFX do podpisu: '.openssl_error_string());
            }
            $privateKey = openssl_pkey_get_private($certs['pkey']);
            if (! $privateKey) {
                throw new \RuntimeException('Nie można wyciągnąć klucza prywatnego z PFX.');
            }
            $certPem = $certs['cert'];
        }

        $certResource = openssl_x509_read($certPem);
        if (! $certResource) {
            throw new \RuntimeException('Nie można odczytać certyfikatu publicznego.');
        }

        $certDer = '';
        openssl_x509_export($certResource, $certDer);
        $certBase64 = self::pemToBase64($certDer);
        $certInfo = openssl_x509_parse($certResource);

        $keyDetails = openssl_pkey_get_details($privateKey);
        $keyType = $keyDetails['type'] ?? OPENSSL_KEYTYPE_RSA;
        $sigAlgoUri = $keyType === OPENSSL_KEYTYPE_EC ? self::SIG_ECDSA_SHA256 : self::SIG_RSA_SHA256;

        // ─── 2. Document digest (enveloped-signature transform = noop
        //         na oryginalnym XML, potem inclusive C14N, potem SHA-256) ──
        $origDoc = new \DOMDocument;
        $origDoc->loadXML($xml);
        $docCanonical = (string) $origDoc->C14N(false, false);
        $docDigest = base64_encode(hash('sha256', $docCanonical, true));

        // ─── 3. SignedProperties XML ──────────────────────────────
        $certDigest = base64_encode(hash('sha256', base64_decode($certBase64), true));
        $issuerName = self::buildIssuerString($certInfo['issuer'] ?? []);
        $serialNumber = (string) ($certInfo['serialNumber'] ?? '');
        $signingTime = gmdate('Y-m-d\TH:i:s\Z');

        $signedPropsXml = '<xades:SignedProperties Id="SignedProperties">'
            .'<xades:SignedSignatureProperties>'
            .'<xades:SigningTime>'.$signingTime.'</xades:SigningTime>'
            .'<xades:SigningCertificate><xades:Cert>'
            .'<xades:CertDigest>'
            .'<ds:DigestMethod Algorithm="'.self::DIGEST_SHA256.'"/>'
            .'<ds:DigestValue>'.$certDigest.'</ds:DigestValue>'
            .'</xades:CertDigest>'
            .'<xades:IssuerSerial>'
            .'<ds:X509IssuerName>'.htmlspecialchars($issuerName, ENT_XML1).'</ds:X509IssuerName>'
            .'<ds:X509SerialNumber>'.$serialNumber.'</ds:X509SerialNumber>'
            .'</xades:IssuerSerial>'
            .'</xades:Cert></xades:SigningCertificate>'
            .'</xades:SignedSignatureProperties>'
            .'</xades:SignedProperties>';

        // ─── 4. Compute SP digest w pełnym kontekście DOM ──────────
        $closingTag = '</AuthTokenRequest>';
        $pos = strrpos($xml, $closingTag);
        if ($pos === false) {
            throw new \RuntimeException('Brak zamykającego znacznika AuthTokenRequest w XML.');
        }
        $tempSig = '<ds:Signature xmlns:ds="'.self::NS_DSIG.'" Id="Signature">'
            .'<ds:Object><xades:QualifyingProperties xmlns:xades="'.self::NS_XADES.'" Target="#Signature">'
            .$signedPropsXml
            .'</xades:QualifyingProperties></ds:Object></ds:Signature>';
        $tempDoc = new \DOMDocument;
        $tempDoc->loadXML(substr($xml, 0, $pos).$tempSig.$closingTag);
        $xpath = new \DOMXPath($tempDoc);
        $xpath->registerNamespace('xades', self::NS_XADES);
        $spNodes = $xpath->query('//xades:SignedProperties[@Id="SignedProperties"]');
        if ($spNodes->length === 0) {
            throw new \RuntimeException('Nie znaleziono SignedProperties w DOM.');
        }
        $spCanonical = (string) $spNodes->item(0)->C14N(false, false);
        $spDigest = base64_encode(hash('sha256', $spCanonical, true));

        // ─── 5. SignedInfo ────────────────────────────────────────
        $signedInfoXml = '<ds:SignedInfo xmlns:ds="'.self::NS_DSIG.'">'
            .'<ds:CanonicalizationMethod Algorithm="'.self::C14N_INCLUSIVE.'"/>'
            .'<ds:SignatureMethod Algorithm="'.$sigAlgoUri.'"/>'
            .'<ds:Reference URI="">'
            .'<ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/></ds:Transforms>'
            .'<ds:DigestMethod Algorithm="'.self::DIGEST_SHA256.'"/>'
            .'<ds:DigestValue>'.$docDigest.'</ds:DigestValue>'
            .'</ds:Reference>'
            .'<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#SignedProperties">'
            .'<ds:DigestMethod Algorithm="'.self::DIGEST_SHA256.'"/>'
            .'<ds:DigestValue>'.$spDigest.'</ds:DigestValue>'
            .'</ds:Reference>'
            .'</ds:SignedInfo>';

        // ─── 6. Canonicalize SignedInfo w pełnym DOM, sign ─────────
        $fullSig = '<ds:Signature xmlns:ds="'.self::NS_DSIG.'" Id="Signature">'
            .$signedInfoXml
            .'<ds:SignatureValue>PLACEHOLDER</ds:SignatureValue>'
            .'<ds:KeyInfo><ds:X509Data><ds:X509Certificate>'.$certBase64.'</ds:X509Certificate></ds:X509Data></ds:KeyInfo>'
            .'<ds:Object><xades:QualifyingProperties xmlns:xades="'.self::NS_XADES.'" Target="#Signature">'
            .$signedPropsXml
            .'</xades:QualifyingProperties></ds:Object>'
            .'</ds:Signature>';
        $fullDoc = new \DOMDocument;
        $fullDoc->loadXML(substr($xml, 0, $pos).$fullSig.$closingTag);
        $xpathFull = new \DOMXPath($fullDoc);
        $xpathFull->registerNamespace('ds', self::NS_DSIG);
        $siNodes = $xpathFull->query('//ds:SignedInfo');
        if ($siNodes->length === 0) {
            throw new \RuntimeException('Nie znaleziono SignedInfo w DOM.');
        }
        $siCanonical = (string) $siNodes->item(0)->C14N(false, false);

        $signatureRaw = '';
        if (! openssl_sign($siCanonical, $signatureRaw, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('XML signing failed: '.openssl_error_string());
        }

        // ECDSA: PHP zwraca DER, KSeF/XML DSig oczekuje IEEE P1363 (r||s).
        if ($keyType === OPENSSL_KEYTYPE_EC) {
            $signatureRaw = self::ecDerToP1363($signatureRaw, $keyDetails);
        }
        $sigB64 = base64_encode($signatureRaw);

        // ─── 7. Złóż finalną Signature ────────────────────────────
        $signatureXml = '<ds:Signature xmlns:ds="'.self::NS_DSIG.'" Id="Signature">'
            .$signedInfoXml
            .'<ds:SignatureValue>'.$sigB64.'</ds:SignatureValue>'
            .'<ds:KeyInfo><ds:X509Data><ds:X509Certificate>'.$certBase64.'</ds:X509Certificate></ds:X509Data></ds:KeyInfo>'
            .'<ds:Object><xades:QualifyingProperties xmlns:xades="'.self::NS_XADES.'" Target="#Signature">'
            .$signedPropsXml
            .'</xades:QualifyingProperties></ds:Object>'
            .'</ds:Signature>';

        return substr($xml, 0, $pos).$signatureXml.$closingTag;
    }

    private static function ecDerToP1363(string $der, array $keyDetails): string
    {
        $bits = $keyDetails['bits'] ?? 256;
        $size = (int) ceil($bits / 8); // 32 dla P-256, 48 dla P-384, 66 dla P-521

        $offset = 0;
        if (ord($der[$offset]) !== 0x30) {
            throw new \RuntimeException('Invalid ECDSA DER: oczekiwano SEQUENCE.');
        }
        $offset++;
        $seqLen = ord($der[$offset]);
        $offset++;
        if ($seqLen & 0x80) {
            $offset += ($seqLen & 0x7F);
        }
        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid ECDSA DER: oczekiwano INTEGER r.');
        }
        $offset++;
        $rLen = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;
        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid ECDSA DER: oczekiwano INTEGER s.');
        }
        $offset++;
        $sLen = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, $size, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, $size, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }

    public static function pemToBase64(string $pem): string
    {
        $stripped = (string) preg_replace('/-----(BEGIN|END)\s+[A-Z\s]+-----/', '', $pem);

        return trim(str_replace(["\r", "\n", ' '], '', $stripped));
    }

    /**
     * @param  array<string,mixed>  $issuer
     */
    private static function buildIssuerString(array $issuer): string
    {
        $parts = [];
        foreach (['CN', 'O', 'OU', 'L', 'ST', 'C'] as $key) {
            if (isset($issuer[$key])) {
                $val = is_array($issuer[$key]) ? implode(', ', $issuer[$key]) : $issuer[$key];
                $parts[] = "{$key}={$val}";
            }
        }

        return implode(', ', $parts);
    }
}
