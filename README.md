# Vitrine ClickCurac sur votre propre domaine

Ces deux fichiers affichent, sur le domaine de votre restaurant (par exemple `bistrotdeslilas.fr`), la vitrine que
vous gérez dans votre espace [ClickCurac](https://clickcurac.fr) : accueil, carte, horaires, contact, pages, photos.

**Les fichiers sont les mêmes pour tous les restaurants.** Vous les installez une seule fois. Ensuite, tout se modifie
dans le backoffice ClickCurac, sans jamais revenir sur l'hébergement.

| Fichier | Rôle |
|---|---|
| `index.php` | Affiche la vitrine reliée à ce domaine (pages, plan du site, `robots.txt`, anciennes adresses). |
| `.htaccess` | Envoie toutes les adresses vers `index.php`, passe le site en https et retire « www ». |

## Avant de commencer

- Un espace ClickCurac avec une **vitrine**. Pas encore d'espace ? [Créer la vitrine de mon restaurant](https://clickcurac.fr/commande/?ajout=site:0&utm_source=github&utm_medium=readme).
- Votre **domaine enregistré** dans votre espace (Vitrine → Réglages). Pour l'instant, demandez-le à ClickCurac depuis
  la messagerie du backoffice.
- Un hébergement avec **PHP 7.4 ou plus récent** (avec cURL ou `allow_url_fopen`) et **Apache** qui lit les fichiers
  `.htaccess`. C'est le cas chez la plupart des hébergeurs (OVH, o2switch, Hostinger, IONOS, LWS…).
- Le **certificat https** activé pour le domaine chez l'hébergeur (gratuit chez la plupart d'entre eux).

## Installation

Gardez d'abord une copie de votre ancien site, puis **videz le dossier racine** du domaine (souvent `www` ou
`public_html`). Les anciens fichiers ne doivent plus y être, sinon ils s'afficheraient à la place de la vitrine.

### Option 1 : par FTP (FileZilla, gestionnaire de fichiers de l'hébergeur)

1. Téléchargez [`index.php`](https://raw.githubusercontent.com/ivantrasnitu/clickcurac-vitrine/main/index.php) et
   [`.htaccess`](https://raw.githubusercontent.com/ivantrasnitu/clickcurac-vitrine/main/.htaccess)
   (ou tout le dépôt : bouton **Code → Download ZIP**).
2. Déposez les deux fichiers à la racine du domaine.
   Le fichier `.htaccess` commence par un point : il est souvent masqué, activez l'affichage des fichiers cachés.

### Option 2 : en ligne de commande (SSH)

```sh
cd ~/public_html   # dossier racine du domaine
wget -q https://raw.githubusercontent.com/ivantrasnitu/clickcurac-vitrine/main/index.php
wget -q https://raw.githubusercontent.com/ivantrasnitu/clickcurac-vitrine/main/.htaccess
```

(`curl -fsSLO <adresse>` fonctionne aussi à la place de `wget -q`.)

### Option 3 : avec Git

```sh
cd ~/public_html
git clone https://github.com/ivantrasnitu/clickcurac-vitrine.git .
```

Pour une mise à jour plus tard : `git pull` dans le même dossier.

## Vérifier

Ouvrez votre domaine dans le navigateur :

- **votre vitrine s'affiche** : c'est terminé ;
- **« Votre site est presque prêt »** : les fichiers sont bien installés, mais le domaine n'est pas encore relié à
  une vitrine. Faites-le enregistrer dans votre espace, puis publiez la vitrine ;
- **« Page introuvable » ClickCurac** : le domaine est relié, mais la vitrine n'est pas publiée (Vitrine → Réglages)
  ou n'est plus comprise dans l'abonnement.

## Anciennes adresses et QR codes déjà imprimés

Votre ancien site avait d'autres adresses, par exemple un QR code sur les tables vers `menu.php?lang=en` ? Dans le
backoffice, **Vitrine → Référencement → Anciennes adresses**, indiquez vers quelle page chacune mène. La langue
demandée est gardée : `menu.php?lang=en` ouvre la carte en anglais. Google suit aussi ces redirections : vos pages
gardent leur place dans les résultats.

## Questions fréquentes

**Faut-il refaire quelque chose quand je modifie ma vitrine ?**
Non. Les fichiers lisent la vitrine en direct : textes, photos, horaires et cartes sont à jour dès que vous
enregistrez dans le backoffice.

**Le site est-il bien référencé par Google ?**
Oui. Les pages sont complètes à l'ouverture (pas de chargement après coup). Elles portent l'adresse de votre domaine
comme référence, un plan du site (`/sitemap.xml`) et les informations de votre restaurant (adresse, horaires, carte)
sous une forme que Google comprend.

**Et les visites ?**
Elles sont comptées dans Vitrine → Visites, sans cookie ni bandeau. Seules les pages vues et leur provenance
(Google, Instagram, ancien QR code…) sont comptées, sans aucune donnée sur les visiteurs.

**Mon hébergeur n'utilise pas Apache (Nginx) ?**
Faites passer toutes les adresses à `index.php` (équivalent de `try_files $uri /index.php?$query_string;`). Au
besoin, écrivez-nous.

**Peut-on modifier ces fichiers ?**
Ce n'est pas nécessaire et c'est déconseillé : une mise à jour remplacerait vos changements. Tout se règle dans le
backoffice.

## Aide

Depuis votre espace ClickCurac, ouvrez la **messagerie** : nous répondons rapidement.
Site : [clickcurac.fr](https://clickcurac.fr/?utm_source=github&utm_medium=readme)
