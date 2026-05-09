# hovera — Guide pour employé d'écurie

> Bienvenue. Ce guide concerne les rôles **Instructeur / Employé / Manager / Lecture seule** dans le panneau écurie `/app`. Le guide complet du propriétaire est disponible sous `/app/help` depuis un compte propriétaire / admin.

---

## 1. Connexion

1. Ouvrez `https://app.hovera.app/app/login`.
2. E-mail et mot de passe — l'invitation est arrivée de l'écurie par e-mail ; au premier clic, vous définissez votre propre mot de passe.
3. Après connexion, vous arrivez sur la page d'accueil correspondant à votre rôle.

### Réinitialisation du mot de passe

`https://app.hovera.app/forgot-password` ou bouton « Mot de passe oublié » sur l'écran de connexion. Lien valide **60 minutes**.

### 2FA (facultatif)

Menu utilisateur (haut à droite) → **« Authentification à deux facteurs »** → scannez le QR code avec une app TOTP (Google Authenticator / 1Password / Authy). Sauvegardez les codes de récupération.

---

## 2. Rôles dans l'écurie

Selon le rôle, vous voyez différents menus et actions :

| Rôle | Ce que vous voyez | Ce que vous pouvez faire |
|---|---|---|
| **Manager** | Tout sauf paramètres écurie et employés | Gérer calendrier, factures, clients, chevaux |
| **Instructeur** | Calendrier, vos réservations, vos chevaux, clients | Modifier vos réservations, ajouter activités sur les chevaux que vous travaillez |
| **Employé** | Fiche cheval (journal d'activité), calendrier en lecture seule | Saisir pansage, alimentation, paddock |
| **Lecture seule** | Tout en lecture | Aucune modification |

> Si vous êtes **maréchal-ferrant / vétérinaire**, il existe un guide séparé (rôle `vet` → guide spécialiste).

---

## 3. Routine quotidienne — Instructeur

### 3.1 Calendrier du jour

**Chemin :** `/app/calendar` (jour / semaine, regroupé par manège / instructeur).

- Cliquer une entrée → ouvre les détails (participants, cheval, statut).
- Créneau vide → cliquer, ajouter une nouvelle réservation (si le rôle le permet).

### 3.2 Liste des réservations

**Chemin :** `/app/calendar-entries` — tableau complet. Filtres : type, statut, « mes seules », « à venir ».

Statuts : *Demandée* → *Confirmée* → *Effectuée* / *Annulée* / *Absence*.

Après la leçon, vous marquez le statut :
- **Effectuée** — il était présent,
- **Absence** — le client ne s'est pas présenté sans annulation,
- **Annulée** — le client a annulé (avec motif).

### 3.3 Vos chevaux

**Chemin :** `/app/horses`. Par défaut filtré sur « Mes » (ceux que vous travaillez).

Fiche cheval → onglet **Activités** :
- ➕ **« Ajouter une activité »** → type (pansage / alimentation / paddock / autre), note.

> Les 7 derniers jours d'activités sont visibles pour le propriétaire dans le portail client.

---

## 4. Routine quotidienne — Employé

Votre section principale : **Fiche cheval → Activités**.

Vous saisissez les soins quotidiens :

- **Alimentation** — ex. « 6:00 – foin + avoine »,
- **Pansage** — durée + notes,
- **Paddock** — quel paddock, combien d'heures,
- **Autres** — ex. « Fer perdu, appeler le maréchal ».

Les entrées arrivent immédiatement dans la timeline du cheval. L'écurie et le propriétaire les voient.

> Si vous remarquez quelque chose d'inquiétant (boiterie, toux) — ajoutez une entrée **et** envoyez un message via la fiche cheval → onglet **Messages**. L'écurie reçoit une notification e-mail.

---

## 5. Routine quotidienne — Manager

Le manager a l'accès le plus large hors paramètres système :

- **Calendrier + réservations** — gestion complète, déplacement des entrées d'autrui,
- **Clients** — ajouter, modifier, générer des magic links vers le portail,
- **Chevaux** — ajouter, modifier, documents, santé,
- **Factures** — émettre, envoyer par e-mail, marquer payées,
- **Cartes** — vendre, annuler, modifier validité.

Description complète de chaque module dans le guide propriétaire (`/app/help` depuis un compte propriétaire/admin).

---

## 6. Messages et notifications

### 6.1 Messages dans la fiche cheval

Chaque cheval a un chat entre l'écurie et le propriétaire. Vous pouvez :
- lire l'historique,
- écrire un message (ex. « Paddock 3h aujourd'hui » — possible aussi via Activités),
- joindre des fichiers (PDF/JPG/PNG, max 10 Mo chacun, jusqu'à 5 fichiers).

### 6.2 Notifications e-mail

Par défaut vous recevez un e-mail pour :
- nouvelle réservation chez vous (Instructeur),
- réponse client à votre message,
- notification de l'écurie (« Demain 14h, le maréchal vient »).

Menu utilisateur → **« Notifications »** vous permet de désactiver des catégories.

---

## 7. Langue de l'interface

Menu utilisateur (haut à droite) → **Polski / English / Deutsch / Français**. La préférence est stockée par utilisateur — persiste à la prochaine connexion.

> Les clients voient le portail dans la langue définie par l'écurie (ou la leur, si disponible). Votre changement n'affecte pas ce qu'ils voient.

---

## 8. Sécurité

- **Mot de passe** — min. 8 caractères ; ne pas réutiliser depuis d'autres services.
- **Reset** — `/forgot-password` (mail avec lien, TTL 60 min).
- **2FA** — fortement recommandée pour rôles Manager / Instructeur.
- **Déconnexion** — menu utilisateur → « Déconnexion » ; la session expire aussi après 8h d'inactivité.

> **Ne partagez jamais votre mot de passe avec des collègues.** Chacun a son propre compte — important pour le journal d'audit (qui a saisi une mauvaise facture, qui a enregistré l'activité).

---

## 9. Problèmes courants

| Problème | Que faire |
|---|---|
| Mot de passe oublié | `/forgot-password` → mail avec lien |
| Mail de reset n'arrive pas | Vérifier spam ; demander au propriétaire |
| Réservation invisible | Vérifier filtre « mes seules » — décocher si besoin |
| « Pas de permission » à la modification | Le rôle ne le permet pas — demander à l'admin |
| Client dit qu'il n'a pas reçu d'e-mail | Vérifier l'adresse dans la fiche client ; essayer « Renvoyer » |

---

## 9a. Nouveaux modules qui vous concernent

- **Plan d'alimentation** — onglet « Plan d'alimentation » sur la fiche cheval. Instructeur/Manager édite ; Employé lit et exécute. Le propriétaire le voit dans le portail.
- **Stock de fourrage** — `/app/feed-inventory`. Sortie quotidienne : **« + Mouvement »** → « Consommation » → quantité → valider. Stock diminue. Articles sous le seuil → badge dans la barre latérale.
- **Poids du cheval** — onglet **Poids** (kg + sangle optionnelle). Colonne « Évolution » : 🟢 prise / 🟡 perte / ⚪ stable.
- **Galerie photos** — onglet **Galerie** ; propriétaire voit la grille dans le portail.
- **Tableau « Aujourd'hui »** (`/app`) — 4 tuiles KPI + tableau des réservations du jour.

---

## 9b. Ce que vous voyez dans le panneau — votre rôle

La barre latérale est filtrée par rôle :

| | Instructeur | Employé | Manager | Lecture seule |
|---|:-:|:-:|:-:|:-:|
| Chevaux · Soins · Plan du jour · Réservations | ✓ | ✓ | ✓ | ✓ |
| Clients | ✓ | — | ✓ | ✓ |
| Boxes · Tarifs · Récurrentes · Instructeurs · Manèges | ✓ | — | ✓ | ✓ |
| Stock fourrage | — | ✓ | ✓ | ✓ |
| Spécialistes · Modèles de soins | — | — | ✓ | ✓ |
| Factures · Cartes · Rapports | — | — | ✓ | ✓ |
| Facturation en masse | — | — | ✓ | — |
| Paramètres · Employés | — | — | — | — |

---

## 10. Support

- **Votre écurie** — propriétaire / admin (contact dans la fiche écurie),
- **hovera (technique)** — `support@hovera.app`.

---

*La documentation est mise à jour avec les nouvelles fonctionnalités. Version dans le pied de page du panneau.*

Autres rôles : guide **propriétaire / admin** (`/app/help` depuis un compte propriétaire/admin), guide **spécialiste** (`/app/help` depuis un compte vet) et **portail client** (`/s/{slug}/portal/help`).
