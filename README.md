# ✨ Wingardium Newsletter

Un plugin newsletter magique pour propulser vos envois d’emails, gérer vos abonnés, configurer du SMTP, prévisualiser et éditer des templates en direct, et bien plus.

## 📚 Sommaire

- [📜 Description](#description)
- [🚀 Fonctionnalités Principales](#fonctionnalites-principales)
- [⚙️ Installation & Configuration](#installation--configuration)
- [🛠️ Utilisation](#utilisation)
- [🎨 Personnalisation des Templates](#personnalisation-des-templates)
- [🔍 Recherche Dynamique (AJAX)](#recherche-dynamique-ajax)
- [🤝 Contribuer / Remerciements](#contribuer--remerciements)
- [📄 License](#license)

## 📜 Description

Wingardium Newsletter est un plugin WordPress qui vous permet de mettre en place une newsletter de manière simple et puissante. Il propose :

- Un formulaire d’abonnement (via shortcode)
- Un système de gestion des inscrits complet (avec recherche AJAX, désinscription, etc.)
- Des emails automatiques (inscription / désinscription)
- La personnalisation du contenu et des templates HTML
- Le support du SMTP (pour sécuriser et fiabiliser vos envois)
- Et tout cela dans une interface conviviale accessible depuis votre back-office WordPress.

## 🚀 Fonctionnalités Principales

- Formulaire d’inscription via shortcode [wingardium_subscribe].
- Gestion des abonnés avec recherche dynamique (AJAX), affichage en tableau, bouton “Désinscrire”, etc.
- Envoi de newsletters à tous les abonnés (vous pouvez insérer un lien de désinscription manuellement, ou l’intégrer dans vos templates).
- Historique d’envois (sujet, contenu, date, nombre de destinataires).
- Support SMTP : possibilité de configurer votre propre serveur SMTP (host/port/encryption).
- Templates HTML pour personnaliser vos emails et newsletters.
- Édition en direct (vous pouvez modifier le contenu HTML dans l’admin).
- Téléversement de nouveaux templates via l’interface du plugin.
- Prévisualisation en direct (iframes) pour chaque template.
- Emails automatiques : envoyés lors de l’inscription ou de la désinscription.
- Recherche AJAX : les abonnés et les templates peuvent être filtrés sans rechargement de la page.
- Détection GitHub Updater : si le plugin GitHub Updater n’est pas présent, une alerte rouge s’affichera pour vous conseiller son installation.

## ⚙️ Installation & Configuration

Téléchargez le plugin ou clonez le dépôt GitHub dans le dossier wp-content/plugins/.
Activez le plugin depuis l’onglet “Extensions” de votre admin WordPress.
Dans le menu WordPress, vous verrez apparaître “Wingardium Newsletter”. Cliquez dessus pour accéder aux réglages.
Configurez les différents onglets :
- Formulaire : personnaliser le texte, le label, le bouton, etc.
- SMTP : activer ou non l’envoi SMTP, renseigner host, port, nom d’utilisateur, etc.
- Templates : sélectionner le template principal pour la newsletter, téléverser ou éditer vos propres templates.
- Paramètres d’Envoi : définir le nom de l’expéditeur, l’adresse “From”, une adresse BCC, etc.
- Emails Inscription / Désinscription : personnaliser le sujet et le contenu envoyés automatiquement.

Remarque :
Le dossier templates/ doit être accessible en écriture (droits CHMOD 755 ou 775 selon votre hébergement) si vous voulez téléverser et éditer les fichiers HTML directement depuis l’interface.

## 🛠️ Utilisation

1. Formulaire d’inscription
   Placez le shortcode suivant où vous le souhaitez (page, article, widget texte…) :

[wingardium_subscribe]
Les visiteurs pourront ainsi s’inscrire à votre newsletter. Vous pouvez personnaliser l’apparence via vos CSS ou via les options du plugin (onglet “Formulaire”).

2. Gérer vos abonnés et envoyer une newsletter
   Rendez-vous dans Wingardium Newsletter > Abonnés & Newsletter :

Envoyer une Newsletter : Renseignez le sujet, le contenu HTML (vous pouvez utiliser l’éditeur WP), puis cliquez sur “Envoyer la Newsletter”.
Gérer les abonnés : Une liste affichera tous les inscrits. Vous pouvez rechercher un abonné, et cliquer sur “Désinscrire” pour le retirer de la liste.
3. Historique des envois
   Dans l’onglet “Historique”, vous pouvez consulter la date, le sujet et le nombre de destinataires de chaque envoi.

## 🎨 Personnalisation des Templates

Les templates se trouvent dans le dossier templates/.
Leur nom doit suivre le format template_*.html (par exemple template_modern.html).
Placeholders à inclure au minimum :
{MESSAGE} : le contenu principal de l’email (ex. texte de votre newsletter).
{UNSUBSCRIBE_LINK} : le lien de désinscription.
En back-office, onglet “Templates” :
Vous pouvez téléverser un nouveau template au format .html.
Vous pouvez éditer un template existant (un éditeur de texte s’ouvre).
Vous pouvez prévisualiser en direct via un iframe.

## 🔍 Recherche Dynamique (AJAX)

Abonnés : Dans l’onglet “Abonnés & Newsletter”, une barre de recherche vous permet de filtrer les inscrits sans recharger la page.
Templates : Dans l’onglet “Templates”, vous pouvez également filtrer les fichiers template_*.html en direct, pour rapidement retrouver celui que vous souhaitez éditer.
Le plugin utilise des endpoints wp_ajax_... pour rafraîchir les listes côté admin.

## 🤝 Contribuer / Remerciements

Idées d’évolution : gestion de segments, import/export d’adresses, intégration plus poussée…
Contributions : pull requests et issues sont les bienvenues sur GitHub.
Un grand merci à toute la communauté WordPress et à vous qui utilisez Wingardium Newsletter !

## 📄 License

Ce plugin est distribué sous la Licence GPL v2 (ou supérieure).
Vous êtes libres de l’utiliser, le modifier, et de le redistribuer selon les termes de la licence.