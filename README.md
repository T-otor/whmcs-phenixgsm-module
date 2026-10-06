# PHENIX Centralizer

API REST FastAPI pour connecter WHMCS à l'API GSM PHENIX v2.9.

## Configuration et démarrage

Renseigner `.env` avec les identifiants API PHENIX (`PHENIX_USERNAME`, `PHENIX_PASSWORD`, `PARTENAIRE_ID`) et une clé privée WHMCS `API_KEY` d'au moins 32 caractères. Le dépôt ignore `.env`; ne pas transmettre cette clé au navigateur ni la versionner.

```powershell
Copy-Item .env.example .env
$bytes = [System.Security.Cryptography.RandomNumberGenerator]::GetBytes(48)
$env:API_KEY = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+','-').Replace('/','_')
Add-Content .env "API_KEY=$env:API_KEY"
docker compose up --build -d
```

L'API écoute sur `http://localhost:8000`; documentation interactive sur `/docs`; `/health` est public pour le healthcheck. Toutes les routes sous `/api` exigent `X-API-Key: <API_KEY>`. Garder le port accessible uniquement depuis WHMCS ou placer un reverse proxy HTTPS devant le service lorsqu'il doit être joint à distance.

Le module serveur WHMCS et son dashboard administrateur prêt à installer sont dans [`whmcs/`](whmcs/README.md). Le dashboard affiche le parc GSM ainsi que les portabilités entrantes et sortantes.

Le hook `hooks/phenix-dashboard.php` lit `PHENIX_CENTRALIZER_URL` (défaut `http://127.0.0.1:8000/api`) et `PHENIX_CENTRALIZER_API_KEY` dans l'environnement PHP-FPM/WHMCS. Copier le hook dans le répertoire `includes/hooks` de WHMCS et configurer ces variables côté serveur.

## Routes principales

Toutes les routes ci-dessous sont préfixées par `/api` et nécessitent la clé API.

| Méthode | Route | Fonction |
|---|---|---|
| GET | `/offers?operator=ORANGE` | Catalogue d'options GSM |
| GET | `/offers/profiles/{id}` | Profil technique et produits |
| GET | `/offers/zones/{operator}` | Zones de recharge |
| GET | `/offers/recharges/{operator}?zone=ZoneA` | Codes de recharge par opérateur |
| GET | `/lines/{msisdn}` | État complet de la ligne |
| GET | `/lines` | Parc de lignes |
| GET | `/lines/status/{msisdn}` | État simplifié |
| POST | `/lines/activate` | Activation (`CreateNA`) ou portabilité entrante (`CreateNP`) |
| POST | `/lines/{msisdn}/options` | Remplacement de la liste finale des options |
| POST | `/lines/{msisdn}/options/changes` | Ajout/suppression ciblé d'options |
| POST | `/lines/{msisdn}/suspend`, `/resume`, `/cancel` | Suspension, réactivation, résiliation |
| POST | `/lines/{msisdn}/sim-swap` | Changement de SIM |
| GET | `/lines/{msisdn}/rio` | RIO |
| GET | `/requests/{id}` | Suivi d'une commande GSM |
| GET | `/portabilities/in`, `/portabilities/out` | Portabilités entrantes/sortantes |
| GET | `/consumption/sdtr?msisdn=...` | Consommation data temps réel (Orange) |
| GET | `/consumption/cdr?msisdn=...&month=MMYYYY` | Consommation mensuelle CDR |
| GET | `/consumption/cdr/daily` et `/details` | Détail CDR |
| GET | `/esim/qrcode/{msisdn}` | QR eSIM PDF par numéro |
| GET | `/esim/qrcode-by-sim/{sim_sn}` | QR eSIM PDF par SIM SN |
| GET | `/esim/qrcode/text?msisdn=...` | Code d'activation eSIM |
| GET | `/stock/sims`, `/stock/sims/{sim_sn}` | Stock et détail SIM/eSIM |
| GET | `/stock/orders/{id}` et `/sims` | Commande SIM et cartes reçues |
| POST | `/customers/search` | Recherche de clients PHENIX |

Les corps JSON des opérations POST suivent les champs PHENIX décrits dans la documentation v2.9; `partenaireId` est toujours remplacé par la valeur serveur configurée. Les erreurs PHENIX sont relayées avec un statut HTTP correspondant, les erreurs réseau comme 502 et les expirations de délai comme 504.

Exemple d'appel WHMCS/PHP :

```php
$ch = curl_init('http://127.0.0.1:8000/api/lines/0612345678');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-API-Key: ' . getenv('PHENIX_CENTRALIZER_API_KEY')],
]);
$line = json_decode(curl_exec($ch), true);
curl_close($ch);
```

## Contraintes d'exploitation PHENIX

Les opérations Orange hors suspension doivent être envoyées entre 08:00 et 22:00. Les portabilités sont possibles du lundi au vendredi. Les écritures GSM sont asynchrones dans PHENIX: conserver l'`id` retourné et consulter `/requests/{id}` jusqu'à obtention du statut final.
