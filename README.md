# Sprint 1 - Pop Quiz

## 🎯 Objectif
L'objectif de cet exercice est d'évaluer les compétences acquises durant le premier sprint : [System Setup and Lab Configuration](https://gist.github.com/Bahlaouane-Hamza/94eef1209856dc7ee7a2c168b4537327#file-system-setup-and-lab-configuration-md).

Ce quiz est divisé en **deux chapitres** :
- **Chapitre 1 : Backend** → Gestion des erreurs et optimisation des performances.
- **Chapitre 2 : Frontend** → Résolution de problèmes liés aux requêtes XHR, optimisation du cache et sécurité.

## 📄 Livrables attendus
Vous devez fournir :
- Un rapport détaillant la résolution des problèmes rencontrés.
- Le rapport peut être au format Markdown (`.md`), PDF ou texte structuré.

## ⚙️ Installation

### Prérequis 📌
- **PHP 8** : Voir [Multiple PHP Versions](https://getgrav.org/blog/macos-sequoia-apache-multiple-php-versions) (uniquement la partie PHP, sans serveur web, car nous utiliserons le serveur PHP intégré).
- **PHP Composer** : Voir [Installation Guide](https://getcomposer.org/download/).
- **Node.js 22 & NPM** : Utiliser [nvm](https://github.com/nvm-sh/nvm) pour gérer les versions.

### Installation et exécution

#### Backend (PHP)
```bash
cd backend
composer install
php -S localhost:8888 2>/dev/null
```

#### Frontend (React)

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```


### Chapitre 1: Challenges Backend

**⚠️ Gestion des erreurs**
- La page `http://localhost:8888/broken` renvoie une erreur 500 sans message d'indication. Identifier le message d'erreur et proposer une solution.

**🔄 Gestion des connexions simultanées**
- Effectuer un test Apache Benchmark sur la page `http://localhost:8888/crash` pour simuler des connexions simultanées :
```bash
ab -n 200 -c 10 http://localhost:8888/crash
```
- Identifier l'erreur ou le goulot d'étranglement qui provoque le crash.
- Proposer et mettre en œuvre une solution pour résoudre le problème de ce crash.

### Chapitre 2: Challenges Frontend

**🔍 Appel Fetch / XHR qui ne passent pas sur la route /fetch :**

- Analyser pourquoi les appels XHR ne passent pas sur la route /fetch.
- Identifier les éventuelles erreurs de configuration ou de réseau.
- Proposer une solution pour résoudre ce problème.

**🛑 Problème des appels XHR sur la Page /users :**
- Examiner pourquoi les appels XHR ne passent pas sur la page /users.
- Identifier les éventuelles erreurs de configuration ou de permissions.
- Proposer une solution pour résoudre ce problème.

 **🚀 Optimisation du téléchargement des Assets :**
- Analyser pourquoi les assets sont téléchargés à chaque recharge de page.
- Proposer des méthodes pour optimiser le téléchargement des assets, comme la mise en cache côté client (ce cache doit prendre en considération le rafraîchissement des différents type d'assets: fonts, css, images et JavaScript). L'application sera déployer régulièrement chaque jours et souvent c'est du code JS qui sera modifié. 

**🔐 Correction de la Faille XSS sur la Page /security :**
- La page `/security` contient une image qui provient d'une source non identifié.
- Proposer une solution pour bloqué toutes les images qui ne proviennent pas du host `localhost` en mettant en place une politique de sécurité des contenus (CSP) pour prévenir les attaques XSS.
