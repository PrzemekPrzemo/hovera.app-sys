# hovera — Guide du portail client

> Bienvenue sur le portail client. Vous y trouverez toutes vos réservations, cartes, factures et informations sur vos chevaux. L'écurie qui utilise hovera héberge le portail à l'adresse `https://app.hovera.app/s/{slug-ecurie}/portal`.

---

## 1. Connexion (magic link)

Le portail **n'utilise pas de mot de passe**. Vous vous connectez avec un lien à usage unique envoyé par e-mail :

1. Ouvrez la page de connexion du portail — l'adresse est fournie par l'écurie (ex. `https://app.hovera.app/s/ecuries-pegase/portal/login`).
2. Saisissez votre adresse e-mail (la même que celle enregistrée par l'écurie sur votre fiche client).
3. Cliquez sur **« Envoyer le lien »**.
4. Vérifiez votre boîte mail — vous recevrez un message en quelques secondes.
5. Cliquez sur le lien → vous êtes connecté pour **30 jours**.

> **Le lien est à usage unique et valide 30 minutes.** Si vous le manquez, demandez-en simplement un nouveau.

### Je ne reçois pas l'e-mail — que faire ?

- Vérifiez le dossier **Spam / Promotions / Notifications**.
- Assurez-vous que l'adresse est exactement celle de l'écurie (une faute de frappe = pas d'e-mail).
- Contactez l'écurie — elle peut copier le lien depuis le panneau et vous l'envoyer par SMS.

---

## 2. Tableau de bord

Après connexion, vous voyez un seul écran avec toutes les sections. Chaque section apparaît seulement si elle contient des données.

### 2.1 Réservations à venir

Liste de vos réservations à partir d'aujourd'hui, triées par proximité.

Chaque ligne affiche :
- **date et heure** de début + durée de la leçon,
- **statut** (Demandée / Confirmée),
- **instructeur**, **cheval**, **manège**,
- boutons d'action.

#### Actions

- **Reporter** — ouvre l'écran de report (uniquement pour le statut *Confirmée*). Nous affichons les prochains créneaux libres avec le même instructeur ; choisir un créneau = envoi d'une demande à l'écurie et un e-mail de confirmation.
- **Annuler** — ouvre un formulaire d'annulation sécurisé (lien signé, valide jusqu'au début de la réservation).

> **Important :** « Reporter » et « Annuler » sont disponibles uniquement pour les réservations qui n'ont pas encore commencé.

### 2.2 Vos cartes

Si l'écurie vend des cartes (ex. « 10 leçons »), les cartes actives apparaissent ici. Chaque carte affiche :

- entrées restantes (ex. **7 / 10 restantes**),
- barre de progression,
- date d'expiration,
- statut (Active / Épuisée / Expirée).

La section **« Récemment utilisées »** liste les 5 dernières leçons imputées sur la carte.

### 2.3 Historique des réservations

Leçons déjà passées ou annulées / non honorées. Statuts :

- **Effectuée** — la leçon a eu lieu,
- **Annulée** — vous ou l'écurie avez annulé,
- **Absence** — vous ne vous êtes pas présenté sans annulation préalable.

### 2.4 Factures impayées

Si l'écurie vous a émis des factures non réglées, elles apparaissent ici avec :

- numéro de document,
- date d'émission + date d'échéance (en rouge si dépassée),
- montant dû.

Cliquer sur une ligne ouvre la vue publique (URL signée — sans connexion) avec un bouton **« Payer maintenant »** si l'écurie a configuré une passerelle (Przelewy24 / PayU / Stripe / Mollie).

### 2.5 Messages

Les 5 messages les plus récents des conversations liées aux chevaux. Liste complète : lien **« Tous → »** dans l'en-tête.

### 2.6 Vos chevaux

Chevaux dont vous êtes propriétaire dans cette écurie. Chaque ligne affiche :

- nom, race, âge,
- **pastilles santé** :
  - 🔴 **X en retard** — X soins en retard (vaccins, maréchal, dentiste) — **action requise**,
  - 🟢 **X dans 30 jours** — X soins planifiés ce mois,
  - ⚪ **OK** — tout est à jour.

Si vous avez des messages non lus, une **pastille 📬 X nouveaux messages** apparaît.

Cliquer sur une ligne → fiche du cheval (section 3).

---

## 3. Fiche du cheval

Cliquer sur un cheval depuis le tableau de bord ouvre sa fiche complète. Sections :

### 3.1 Informations de base

- nom, race, robe, date de naissance, sexe,
- puce, n° passeport, UELN,
- box actuel.

### 3.2 Soins & santé (timeline)

Vaccinations, maréchal, dentiste :

- 🔴 **en retard** — date dépassée, à programmer,
- 🟡 **dans 30 jours** — à anticiper,
- 🟢 **à jour** — prochain rendez-vous > 30 jours.

Chaque entrée affiche la date du dernier passage + date suggérée pour le suivant.

### 3.3 Activités

Pansage, exercice, paddock — entrées des 7 derniers jours saisies par l'écurie.

### 3.4 Messages

Chat avec l'écurie au sujet de ce cheval. Vous pouvez :

- consulter l'historique (de l'écurie et de vous),
- écrire un nouveau message (ex. « Merci de le panser avant la leçon »),
- joindre **jusqu'à 5 fichiers** (PDF/JPG/PNG, **max 10 Mo chacun**).

L'écurie reçoit une notification par e-mail ; vous aussi pour leurs réponses.

### 3.5 Documents

Passeport, contrat, assurance, certificats — fichiers PDF/JPG (max 25 Mo).

Actions :
- **Télécharger** n'importe quel document,
- **Téléverser** un nouveau document (passeport, assurance…),
- **Supprimer** les documents que vous avez téléversés (les documents de l'écurie ne peuvent pas être supprimés).

---

## 4. Reporter une réservation

Cliquer sur **« Reporter »** sur une réservation à venir → ouvre un écran avec :

- la date/heure actuelle,
- une liste des **3 à 7 prochains créneaux libres** avec le même instructeur.

Choisissez le créneau souhaité → cliquez **« Envoyer la demande »** :

1. L'écurie reçoit une notification,
2. Vous recevez un e-mail de confirmation,
3. La réservation est déplacée (statut reste *Confirmée*).

> Si aucun créneau ne convient, envoyez un message à l'écurie via la section **Messages** dans la fiche du cheval ou par e-mail direct.

---

## 5. Annuler une réservation

Cliquer sur **« Annuler »** → ouvre une URL signée (lien cryptographiquement signé, valide uniquement jusqu'au début de la leçon).

Le formulaire affiche :
- détails de la réservation,
- champ **« Motif d'annulation »** (facultatif mais utile à l'écurie).

Cliquez **« Confirmer l'annulation »** → le statut devient *Annulée*, l'écurie est notifiée.

> Une annulation bien à l'avance restitue généralement l'entrée sur la carte. Une annulation peu avant la leçon peut entraîner des frais — les conditions sont fixées par l'écurie.

---

## 6. Factures

Cliquer sur une facture depuis le tableau de bord ouvre sa vue publique :

- données complètes (vendeur, acheteur, lignes, totaux, TVA),
- bouton **« Télécharger PDF »**,
- bouton **« Payer maintenant »** (si l'écurie a une passerelle).

### Paiement en ligne

Cliquer sur **« Payer maintenant »** → redirection vers la passerelle (BLIK / carte / virement). Après paiement, vous revenez au portail — le statut de la facture se met à jour automatiquement après la confirmation par webhook.

> Si vous payez par virement classique, utilisez le numéro de compte et la référence de la facture — l'écurie la marquera comme payée manuellement après réconciliation.

---

## 7. Messages — liste complète

Le lien **« Tous → »** ouvre un écran avec tous les fils liés à vos chevaux. Filtres : cheval, non lus.

Cliquer sur un fil ouvre la fiche du cheval, section Messages.

---

## 8. Langue du portail

Le portail parle quatre langues : **polonais / anglais / allemand / français**. Par défaut, la langue de l'écurie est utilisée ; si vous changez, la préférence est enregistrée dans la session.

> Il n'y a pas de sélecteur de langue dans le portail (il est dans le panneau employé) ; si vous voulez une autre langue, demandez à l'écurie de changer le défaut.

---

## 9. Sécurité & confidentialité

- **Connexion par magic link** = aucun mot de passe à retenir, aucun mot de passe à fuiter.
- **Session de 30 jours** — ensuite, vous saisissez à nouveau votre e-mail.
- **Déconnexion** — bouton en haut à droite.
- **Vos données** — vous ne voyez que *vos* réservations, *vos* chevaux, *vos* factures. Même si l'écurie a 100 clients, chacun ne voit que les siens.

> Le portail n'affiche que les données de cette écurie. Si vous êtes client de plusieurs — chacune a son propre URL de portail.

---

## 10. Problèmes courants

| Problème | Que faire |
|---|---|
| Pas d'e-mail avec le lien | Vérifier le spam ; demander à l'écurie d'envoyer le lien manuellement |
| Lien expiré / ne fonctionne pas | Saisir l'e-mail à nouveau — un nouveau sera envoyé |
| « Reporter » n'affiche aucun créneau | L'instructeur est peut-être indisponible — écrivez à l'écurie |
| La pastille « X en retard » ne disparaît pas | L'écurie doit marquer la visite maréchal/vaccin comme effectuée |
| Je ne vois pas une facture | Contactez l'écurie — elle peut la rééditer |
| Pièces jointes >10 Mo n'envoient pas | Compressez la photo / divisez le PDF |

---

## 11. Support

- **Écurie** — contact e-mail / téléphone visible sur la page publique `https://app.hovera.app/s/{slug-ecurie}`,
- **hovera (technique)** — `support@hovera.app`.

---

*La documentation est mise à jour avec les nouvelles fonctionnalités du portail. La version du système est visible dans le pied de page du panneau d'administration de l'écurie.*
