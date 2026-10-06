# Module de provisionnement WHMCS PHENIX GSM

Ce paquet contient un module serveur WHMCS prêt à installer. Il relie les actions d’un produit WHMCS aux routes protégées de PHENIX Centralizer.

Le paquet comprend aussi un tableau de bord d’administration pour voir le parc des lignes et les portabilités.

## Installation

Copier le dossier `modules/servers/phenixgsm` dans le dossier racine de WHMCS, sous `modules/servers/phenixgsm`. Dans WHMCS, ouvrir **Configuration > System Settings > Products/Services**, éditer le produit mobile puis choisir **PHENIX GSM** dans les paramètres du module.

Après avoir activé l’addon admin décrit ci-dessous, configure l’URL et la clé dans ses paramètres. Ces valeurs sont la source partagée utilisée par le dashboard et le module serveur. Les variables d’environnement restent disponibles en repli :

- `PHENIX_CENTRALIZER_URL` : URL complète de l’API, par exemple `https://api.exemple.fr/api`.
- `PHENIX_CENTRALIZER_API_KEY` : même secret que `API_KEY` dans le `.env` de PHENIX Centralizer.

Le secret est lu côté serveur. Il ne doit pas être ajouté à un champ de commande visible du client. Si WHMCS s’exécute sur une autre machine ou dans un autre conteneur, configure `PHENIX_CENTRALIZER_URL` avec une adresse joignable par cette machine (pas `localhost`) et utilise HTTPS.

## Paramètres du produit et choix SIM/eSIM

Dans les paramètres du module du produit, renseigner l’opérateur, l’opération (`CreateNA` pour un nouveau numéro ou `CreateNP` pour une portabilité), le type SIM, le code tarif d’achat et les options PHENIX. Le champ « Produits PHENIX (JSON) » reçoit le tableau `produits` attendu par la documentation GSM v2.9; `[]` active une ligne sans option.

Dans **Configurable Options** du produit, tu peux proposer les choix par abonnement. Les noms doivent être exactement ceux ci-dessous pour être reconnus; les valeurs sélectionnées sont envoyées avec la commande :

- `Type SIM` : `SIM`, `ESIM`, `SIM15D`, `SIM15D_IPFIXE`, `SIM15D_M2M`, `ESIM15D`, `ESIM15D_DataOnly`.
- `Opération GSM` : `CreateNA` ou `CreateNP`.
- `Opérateur` : `ORANGE`, `SFR`, `BTBD` ou `PHENIX`.
- `MSISDN`, `RIO`, `Date de portabilité`, `SIM SN`, `IMSI`, `Code client` selon le scénario.
- `Ligne Data Only`, `IP fixe`, `Code forfait commercial`, `Profil technique GSM ID`, `Durée engagement`, `Code APN` et `Produits PHENIX (JSON)` si ces valeurs varient à la commande.

Les champs service personnalisés WHMCS correspondants peuvent également porter ces noms et servir de source. Les alias `Type de SIM`, `SIM Type`, `Operation`, `Operateur`, `Operator`, `SIM SN / ICCID`, `Numéro SIM`, `Porta Date`, `Options PHENIX (JSON)` et `Forfait GSM Code` sont aussi reconnus.

Pour les SIM physiques, `SIM SN` et `IMSI` sont requis. Pour les types eSIM, ils ne sont pas demandés. Dans l’espace client, le code LPA et son QR sont chargés à la demande quand le client clique sur le bouton; le QR est généré localement dans le navigateur. Crée des champs personnalisés de service WHMCS (type texte) pour les données propres à chaque abonnement si tu ne les proposes pas comme options :

- `MSISDN` pour une portabilité ou un numéro déjà réservé (facultatif en `CreateNA`).
- `RIO` et `Date de portabilité` pour `CreateNP`.
- `SIM SN` et `IMSI` pour une SIM physique.
- `Code client` si le code client PHENIX doit être transmis; sinon le nom de client/site WHMCS est utilisé.

Les valeurs des options configurables sont prioritaires; les réglages module du produit servent de valeurs par défaut.

## Actions prises en charge

- Provisionnement du service : POST `/lines/activate`.
- Suspension WHMCS : POST `/lines/{msisdn}/suspend`.
- Réactivation WHMCS : POST `/lines/{msisdn}/resume`.
- Résiliation WHMCS : POST `/lines/{msisdn}/cancel`.
- Changement de forfait : POST `/lines/{msisdn}/options`.
- Espace client du service : état de la ligne, consommation SDTR pour les lignes Orange et statistiques CDR mensuelles consultables depuis le mois de création du service WHMCS.

PHENIX accepte les commandes GSM de manière asynchrone. Un retour HTTP réussi signifie que PHENIX a accepté la requête; il ne garantit pas encore l’activation réseau finale. Le module enregistre l’identifiant de requête dans les propriétés du service quand la version WHMCS le permet. Les requêtes Orange (hors suspension) sont soumises aux horaires et jours décrits dans la documentation PHENIX. WHMCS conserve son propre état de facturation, qui peut donc précéder l’état final de la ligne.

## Fichiers

- `modules/servers/phenixgsm/phenixgsm.php` : module serveur WHMCS.
- `modules/servers/phenixgsm/clientarea.tpl` : résumé de ligne et sélection des statistiques mensuelles dans la fiche service client.
- `modules/servers/phenixgsm/assets/` : générateur local du QR eSIM et script d’affichage côté navigateur.

Le code cible l’interface documentée des modules de provisionnement WHMCS modernes (PHP 7.4+). Il n’a pas pu être validé avec `php -l` dans cet environnement, car PHP CLI n’y est pas installé. Vérifier le module dans une instance de test WHMCS avant de l’associer à des commandes client réelles.

## Tableau de bord administrateur

Copier `modules/addons/phenixgsmadmin` dans le dossier `modules/addons/` de WHMCS. Dans **Configuration > System Settings > Addon Modules**, activer **PHENIX GSM Dashboard**, puis autoriser les rôles d’administrateurs concernés. Le tableau de bord montre le parc, les lignes actives/suspendues/résiliées, la répartition par opérateur et type de SIM, et les portabilités entrantes/sortantes. Les lignes peuvent être recherchées, filtrées par état/opérateur/type SIM, triées par colonne et exportées en CSV après filtrage. Chaque tableau de portabilités dispose également d’une recherche. Le bouton **Actualiser** relance les appels API.

Renseigner l’URL et la clé du Centralizer dans les paramètres de l’addon. Les mêmes réglages sont utilisés par le dashboard et le module serveur; les variables d’environnement servent de repli. Le tableau n’affiche pas les codes PIN/PUK, les codes d’activation eSIM ni les mots de passe RADIUS. Dans l’espace client, l’intitulé reste « Ma ligne mobile » et les erreurs techniques sont remplacées par un message générique.
