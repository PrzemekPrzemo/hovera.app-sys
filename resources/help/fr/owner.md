# hovera — Manuel du propriétaire d'écurie

> Bienvenue sur hovera. Ce guide vous accompagne dans toutes les fonctionnalités du panneau du propriétaire d'écurie. La plupart des étapes commencent par la navigation principale dans `/app`.

---

## 1. Premiers pas

Après connexion, vous arrivez sur `/app`. La navigation à gauche est divisée en groupes :

- **Écurie** — chevaux, clients, boxes, bâtiments, spécialistes, cartes, soins, tarifs de pension
- **Calendrier** — plan du jour, réservations, séances récurrentes, instructeurs, manèges
- **Finances** — factures
- **Paramètres** — paramètres écurie, facturation, paiements, KSeF, employés

Ordre recommandé :

1. **Paramètres écurie** — informations entreprise, branding, horaires
2. **Bâtiments** — créer au moins un ("Écurie principale")
3. **Boxes** — assigner chacun à un bâtiment
4. **Tarifs de pension** — définir les services (foin, nettoyage box, transport)
5. **Instructeurs + Manèges** — pour que le calendrier accepte des réservations

---

## 2. Paramètres écurie

**Chemin :** `/app/tenant-settings`

Sections :

- **Identification** — nom, raison sociale, n° fiscal
- **Localisation** — pays, langue par défaut, fuseau horaire, devise
- **Branding** — couleur principale, logo, image hero (page publique)
- **Page publique `/s/{slug}`** — slogan, horaires, description, contact, réseaux sociaux
- **Réservation en ligne** — si les clients peuvent réserver via la page publique ; durée des leçons, horaires, délai

---

## 3. Chevaux

**Chemin :** `/app/horses`

### Ajouter un cheval

**"Créer cheval"** → renseigner :

- **Nom** (ex. "Bucéphale")
- **Propriétaire** (sélection client ; vide = écurie est propriétaire)
- **Box** (sélection des boxes actifs)
- **Puce / N° passeport / UELN**
- **Sexe :** Jument / Hongre / Étalon / Étalon reproducteur
- **Race, robe, date de naissance**
- **Pension — services facturables** (multi-sélection depuis le tarif)

### Fiche du cheval (onglets)

- **Soins & santé** — vaccinations, maréchal-ferrant, dentiste (avec suggestion auto de la prochaine échéance)
- **Activités** — affouragement, pansage, mise au paddock
- **Messages** — chat avec propriétaire (envoi par mail)
- **Documents** — passeport, contrat, assurance (PDF/JPG, jusqu'à 25 Mo)

---

## 4. Boxes et bâtiments

### Bâtiments — `/app/buildings`

Une écurie (en tant que lieu) peut avoir plusieurs bâtiments : "Écurie rouge", "Nouvelle écurie", "Pavillon paddock". Chacun est un groupe de boxes.

### Boxes — `/app/boxes`

Champs : bâtiment, nom/numéro, code court, type (intérieur / paddock / extérieur / quarantaine), taille (m²), tarif mensuel pension, actif.

Colonnes :
- **Bâtiment** (avec regroupement — "Écurie rouge" repliable)
- **Nom, type, m²**
- **Statut** — Libre (vert) / Occupé (gris)
- **Sexe du cheval** (si assigné)
- **Pension (PLN/mois)**

---

## 5. Clients

**Chemin :** `/app/clients`

### Ajouter un client

- **Type :** particulier / famille / entreprise
- **Nom complet / raison sociale**
- **E-mail, téléphone**
- **N° fiscal** (avec "Récupérer depuis GUS" si l'API GUS polonaise est configurée)
- **Identification propriétaire de cheval (ARMiR) :**
  - **N° EP** — identifiant producteur attribué par ARMiR lors de l'enregistrement d'un cheval dans la Base Centrale Équine polonaise
  - **PESEL** — repli si pas d'EP

### Fiche client

À l'édition :

- Tous les champs du formulaire
- **Onglet "Chevaux"** — liste des chevaux du client
- Action **"Copier le lien portail"** — génère un magic link (TTL 30 min) à copier
- Action **"Envoyer le lien par e-mail"** — envoie un e-mail avec lien de connexion

---

## 6. Calendrier

### Plan du jour — `/app/calendar`

Vue jour groupée par instructeur/manège. Cliquez sur un créneau vide pour ajouter une réservation.

### Réservations — `/app/calendar-entries`

Tableau complet des réservations. Filtres : type, statut, cheval, instructeur, "à venir uniquement".

**Statuts :** Demandée → Confirmée → Effectuée / Annulée / Absence.

**Types :** Leçon individuelle, Leçon collective, Entraînement, Soins (vétérinaire/maréchal), Événement, Bloc.

### Séances récurrentes — `/app/recurring-calendar-entries`

Crée une série (ex. "École lun. 17:00, hebdomadaire, jusqu'à fin d'année"). Action **"Générer les occurrences"** déploie la série en réservations individuelles.

---

## 7. Spécialistes (maréchaux + vétérinaires)

**Chemin :** `/app/specialists`

### Ajouter

- **Spécialité :** Vétérinaire / Maréchal
- Nom, e-mail, téléphone, couleur calendrier
- **Compte hovera (optionnel)** — si le spécialiste est employé (TenantMembership), le lier à un User. L'employé connecté voit alors **"Mes tâches"**.

---

## 8. Employés

**Chemin :** `/app/team-members` (visible uniquement pour propriétaire / admin)

**"Ajouter un employé"** → e-mail, nom, rôle.

### Rôles

| Rôle | Description |
|---|---|
| **Propriétaire** | Accès complet |
| **Admin** | Idem moins suppression d'écurie |
| **Manager** | Gestion calendrier, factures |
| **Instructeur** | Calendrier, réservations, ses chevaux |
| **Employé** | Journal d'activité |
| **Vétérinaire** | Lié à un Spécialiste + Mes tâches |
| **Lecture seule** | Lecture seule |

### Réinitialisation du mot de passe

Action **"Envoyer le lien de réinitialisation"** envoie un e-mail avec lien vers `/app/password-reset/request`.

---

## 9. Factures

### Configuration — `/app/invoicing-settings`

- **Numérotation FV / Pro forma / Avoir** — modèle avec `{seq}`, `{YYYY}`, `{MM}`, `{prefix}`
- **Reset de numérotation** — Annuel / Mensuel / Jamais
- **Délai de paiement par défaut** en jours
- **Coordonnées vendeur**

### Émission — `/app/invoices`

1. **"Créer"** → choisir client (auto-remplissage acheteur)
2. Ajouter des lignes
3. Enregistrer → statut `Brouillon` → **"Émettre"** → numéro + date
4. **"Envoyer par e-mail"** → client reçoit lien vers vue publique
5. **"Envoyer à KSeF"** (si configuré)

---

## 10. Paiements en ligne — `/app/payment-settings`

Passerelle par défaut : Aucune / Przelewy24 / PayU / Stripe / Mollie. Après configuration, chaque facture envoyée contient un bouton **"Payer maintenant"**.

---

## 11. KSeF (e-facturation polonaise) — `/app/ksef-settings`

Requis : N° fiscal écurie, environnement (test / demo / prod), certificat PFX ou PEM.

---

## 12. Site public et widgets embed

### Page publique — `https://app.hovera.app/s/{slug}`

Rendu automatique basé sur les paramètres écurie.

### Widgets embed

Dans `/app/tenant-settings` → "Widgets" — iframes prêts à coller dans Wordpress / Squarespace :

- **Boxes libres**
- **Réserver en ligne**
- **Tarifs de pension**
- **Liste des instructeurs**

---

## 13. Portail client

Le client se connecte via `https://app.hovera.app/s/{slug}/portal/login` :
- saisit son e-mail
- reçoit un magic link par mail (TTL 30 min)
- clic → tableau de bord

Dans le portail le client voit :
- réservations à venir (avec "Reporter" / "Annuler")
- ses cartes (X / Y restant)
- historique des réservations
- factures impayées
- ses chevaux (avec alertes santé)
- messages

---

## 14. Astuces

- **Langue** — basculer dans le menu utilisateur (PL / EN / DE / FR) ; préférence par utilisateur
- **Support** — support@hovera.app

---

*Version du système : voir le pied de page. Cette documentation est mise à jour avec les nouveaux modules.*
