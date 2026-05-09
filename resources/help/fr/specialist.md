# hovera — Guide pour spécialiste (maréchal-ferrant / vétérinaire)

> Bienvenue. Ce guide concerne le rôle **Spécialiste** — c'est-à-dire un maréchal-ferrant ou un vétérinaire avec un compte hovera (TenantMembership avec le rôle `vet`). Accès au panneau : `https://app.hovera.app/app`.

---

## 1. Connexion

1. Ouvrez `https://app.hovera.app/app/login`.
2. Saisissez e-mail et mot de passe (vous définissez le mot de passe à la première connexion — l'invitation arrive par e-mail de l'écurie).
3. Après connexion, vous arrivez sur la page d'accueil du panneau — par défaut **Mes tâches**.

> **Réinitialisation du mot de passe :** bouton « Mot de passe oublié » ou `https://app.hovera.app/forgot-password`. Nous envoyons un lien (TTL 60 min).

---

## 2. Mes tâches

**Chemin :** `/app` (page d'accueil si votre rôle est `vet`).

Votre vue principale. Le tableau affiche :

- **Aujourd'hui** — visites prévues aujourd'hui,
- **Cette semaine** — 7 prochains jours,
- **En retard** — dates passées non marquées comme effectuées.

Chaque ligne : date, heure, cheval, écurie (si vous travaillez pour plusieurs), type de visite (ferrage / vaccination / contrôle dentaire), statut.

### Actions

- **Ouvrir** — détails de la visite, données du cheval, historique.
- **Marquer comme effectué** — après la visite, cliquer et ajouter une note. Cela fait automatiquement :
  - statut de la visite passe à *Effectuée*,
  - pastille santé du cheval mise à jour (🔴 *X en retard* disparaît),
  - suggère la prochaine visite (ex. ferrage tous les 6 semaines → créneau proposé dans le calendrier).
- **Reporter** — en cas de panne d'équipement, maladie, choisissez un nouveau créneau → l'écurie est notifiée.

---

## 3. Calendrier

**Chemin :** `/app/calendar`

Affiche toutes les visites (les vôtres et les autres) à l'écurie — jour / semaine. Vos entrées sont colorées avec votre couleur (définie par l'écurie dans votre fiche spécialiste).

Filtres :
- **Mes seules** — uniquement les entrées qui vous sont assignées,
- **Type** — maréchal / vétérinaire / autres,
- **Statut** — demandée / confirmée / effectuée.

### Ajouter une entrée

Vous pouvez saisir une visite vous-même (ex. « je suis passé en plus aujourd'hui ») — clic sur un créneau vide → formulaire :
- cheval (depuis la liste de l'écurie),
- type (vaccination / ferrage / dentaire / autre),
- durée (par défaut 30 min),
- note.

> L'écurie voit votre entrée immédiatement — après validation, elle l'inscrit sur la facture (si vous facturez via hovera).

---

## 4. Fiche du cheval

Cliquer sur un cheval dans une visite → ouvre sa fiche. Sections pertinentes pour vous :

### 4.1 Soins & santé (timeline)

Historique complet :
- vaccinations (tétanos, grippe, EHV),
- ferrage (date, maréchal, description),
- visites dentaires,
- autres traitements vétérinaires.

Filtres : type d'entrée, plage de dates, auteur (vous / autre spécialiste / écurie).

### 4.2 Activités

Pansage, exercice, paddock — entrées des 7 derniers jours. Utile, vous voyez ce que le cheval a fait avant votre visite (ex. s'il a été lourdement travaillé la veille du ferrage).

### 4.3 Documents

Passeport, assurance, analyses sanguines — vous pouvez télécharger et consulter.

### 4.4 Messages

Chat avec propriétaire + écurie. Vous pouvez :
- lire les décisions précédentes,
- écrire un message (ex. « Après le ferrage d'hier, repos 24h »),
- joindre des photos (PDF/JPG/PNG, max 10 Mo chacun).

---

## 5. Marquer une visite comme effectuée

Votre flow le plus fréquent.

1. Ouvrez la visite (depuis **Mes tâches** ou le calendrier).
2. Cliquez **« Marquer comme effectué »**.
3. Renseignez :
   - **Date réelle** (par défaut aujourd'hui),
   - **Note** (ex. « Antérieur gauche sans problème, antérieur droit à observer »),
   - **Prochaine visite** — date suggérée (ferrage 6 semaines, vaccination 12 mois). Modifiable.
   - **Coût** (facultatif) — si l'écurie facture via hovera.
4. Confirmer.

Effets automatiques :
- la visite passe à *Effectuée*,
- la timeline du cheval reçoit une entrée,
- la pastille santé est rafraîchie,
- si vous avez fixé une prochaine visite → nouvelle entrée calendrier (statut *Demandée*),
- le propriétaire reçoit une notification e-mail.

---

## 6. Deux situations : employé d'écurie vs. externe

| Aspect | Employé (TenantMembership) | Externe |
|---|---|---|
| Connexion | Oui, compte avec rôle `vet` | Non — l'écurie saisit les visites pour vous |
| Vue « Mes tâches » | Oui | — |
| Notifications e-mail | Oui | Oui (si l'écurie a votre e-mail) |
| Ajouter des entrées calendrier | Oui | Non |
| Voir l'historique du cheval | Oui | Non (sauf partage de documents) |

Comme spécialiste **externe** (indépendant) — l'essentiel de ce guide ne s'applique pas. L'écurie vous contacte par e-mail / téléphone et saisit la visite elle-même.

---

## 7. Plusieurs écuries dans un compte

Si vous travaillez dans **plusieurs écuries** sur hovera, chacune vous invite séparément (TenantMembership distinctes). En haut à gauche du panneau, vous verrez un sélecteur d'écurie (ou `/tenant/select`).

Après le changement, vous ne voyez que les données de l'écurie sélectionnée — chevaux, calendrier, tâches.

---

## 8. Langue de l'interface

Menu utilisateur (haut à droite) → **Polski / English / Deutsch / Français**. La préférence est stockée par utilisateur — persiste après déconnexion.

---

## 9. Sécurité

- **Mot de passe** — minimum 8 caractères ; reset via `/forgot-password`.
- **2FA** — optionnel, dans le menu utilisateur → « Authentification à deux facteurs » (TOTP, ex. Google Authenticator / 1Password).
- **Session** — expire après 8 heures d'inactivité.

---

## 10. Support

- **Écurie** — contact e-mail / téléphone visible sur la page écurie,
- **hovera (technique)** — `support@hovera.app`.

---

*La documentation est mise à jour avec les nouvelles fonctionnalités. La version est visible dans le pied de page du panneau.*

Autres rôles : guide **propriétaire / admin** (`/app/help` depuis un compte propriétaire/admin) et **portail client** (`/s/{slug}/portal/help`).
