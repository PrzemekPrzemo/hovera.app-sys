# Bienvenue sur Hovera Transport

> ⚠️ **Traduction automatique — à vérifier par un locuteur natif avant
> publication.** Les affirmations sont contraignantes, mais la
> formulation n'est pas définitive.

Heureux de vous accueillir. Ce document vous guide à travers vos
premiers jours en tant que transporteur — de l'activation du compte
au premier devis accepté.

## Comment ça fonctionne

- **Inscription → vérification des documents → activation → envoi de
  devis.** La création du compte est instantanée, mais l'envoi de
  devis aux clients est bloqué jusqu'à ce que l'équipe Hovera vérifie
  vos documents (assurance responsabilité civile du transporteur,
  licence de transport, numéro fiscal, immatriculation du véhicule).
  Habituellement 1 jour ouvrable.
- **Hovera est une place de marché intermédiaire, PAS une société de
  transport.** Nous ne possédons pas de véhicules, n'employons pas de
  chauffeurs, n'assumons aucune responsabilité quant à l'exécution
  du transport. Nous vous mettons en relation avec des clients et
  fournissons les outils : panneau de gestion, calculateur de prix,
  générateur de devis, facturation, profil public.
- **Les contrats de transport sont directement entre vous et le
  client final.** Vous émettez la facture sous votre propre numéro
  fiscal, votre propre numérotation, via votre propre KSeF (ou
  e-facturation locale). Hovera n'est pas partie au contrat de
  transport.

## Premiers pas après activation

1. **Ajoutez vos véhicules** dans la section `Véhicules`. Pour chaque
   entrée : nom, plaque, capacité (chevaux), poids total, photos
   (3–6 recommandées dont l'intérieur), équipement (suspension
   pneumatique, caméra). Ces données apparaissent sur les factures
   et sur votre profil public.
2. **Ajoutez vos chauffeurs** dans la section `Chauffeurs`. Chaque
   chauffeur reçoit des notifications sur les missions à venir par
   email/téléphone (via un SMTP séparé `transport@hovera.app`).
3. **Configurez les zones de service** dans `Paramètres → Zones de
   service` (multi-sélection de voïvodies). Crucial — sans cela vous
   ne recevrez pas de leads en mode broadcast.
4. **Configurez le profil public** (`/t/{votre-slug}`). C'est votre
   landing marketing — indexé par Google, partageable sur les
   réseaux sociaux, avec sa propre image OG pour les aperçus.
5. **Connectez une API de routage** dans `Paramètres → Routage`.
   Le plan Solo inclut OpenRouteService gratuit. Pro/Fleet permettent
   votre propre clé Mapbox ou Google Maps Routes.
6. **Émettez votre premier devis.** Ouvrez `Calculateur`, entrez les
   adresses de prise en charge et de livraison, date, nombre de
   chevaux. Cliquez « Enregistrer comme devis » → envoyez le devis
   par email. Le client accepte via une URL signée.

## Place de marché des leads

Un lead = demande d'un client. Arrivée dans l'un de deux modes :

- **Mode broadcast.** Le lead va à tous les transporteurs dont la
  zone de service couvre l'itinéraire. Vous avez jusqu'à 14 jours
  pour répondre.
- **Mode direct.** Le client vous choisit délibérément (étoile,
  profil public, lien partenaire) — le lead va **uniquement à vous**.

Boîte de réception des leads : `Leads` dans la barre latérale gauche
→ action `Répondre avec un devis`.

## Devis et factures

- **Devis :** numérotation `OF/YYYY/MM/NNNN`. PDF auto-généré, envoi
  via `transport@hovera.app`. Le client accepte via URL signée.
- **Facture de transport :** émise après livraison. L'action
  `Émettre une facture à partir du devis` copie les éléments.
  Votre numérotation, votre numéro fiscal. KSeF — bientôt (Phase 9).

## Mini-tableau de bord

`Tableau de bord` — 4 widgets :

- **KPI leads :** nombre de leads hebdomadaire + taux de réussite 30j.
- **Transports à venir :** calendrier 7 jours.
- **Top factures 90j :** classement des mieux payées.
- **Carte de chaleur des routes :** routes les plus fréquentes.

## FAQ

**Hovera assume-t-elle la responsabilité des dommages liés au
transport ?**
Non. Vous êtes le transporteur, vous portez la pleine responsabilité
(Convention CMR + votre assurance).

**Hovera émet-elle des factures de transport ?**
Non. Vous émettez la facture sous votre numéro fiscal.

**Que se passe-t-il si le client ne paie pas ?**
Vous recouvrez directement. Hovera ne joue pas d'intermédiaire
de paiement (pour l'instant — Stripe Connect est sur la roadmap).

**Puis-je avoir plus d'un véhicule ?**
Oui — limite selon le plan : Solo 1, Pro jusqu'à 5, Fleet illimité.

**Puis-je changer de plan ?**
Oui, dans `Paramètres → Abonnement → Changer de plan`.

**Que se passe-t-il si je n'accepte pas un lead sous 14 jours ?**
Le lead expire. Aucune pénalité.

**Puis-je faire des trajets internationaux (DE/CZ/SK) ?**
Dans le MVP — uniquement Pologne. Les trajets internationaux sont
sur la roadmap post-MVP.

**Puis-je avoir à la fois une écurie et une société de transport ?**
Oui — multi-tenant. Bascule en haut de l'écran.

## Support

- **Email support :** `support@hovera.app` (temps de réponse : 1 jour
  ouvrable Solo, 4h Pro, 1h Fleet).
- **Documentation :** `docs.hovera.app/transport`.
- **Statut système :** `status.hovera.app`.
- **Bug reporter dans le panel :** coin inférieur droit.
