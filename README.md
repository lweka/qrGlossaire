# qrGlossaire

## Accès administrateur principal

- URL login admin: `/admin/login`
- URL dashboard admin: `/admin/dashboard`
- URL login client (organisateur): `/login`

Le compte admin principal est stocke dans la table `admins` (separee de `users`).

### Créer le premier admin

Executer en ligne de commande:

```bash
php scripts/create_admin.php --email="admin@email.com" --password="MotDePasseFort123!" --name="Admin Principal"
```

## Crédits invitations et demandes

- Prix unitaire: `$0.30` par invitation.
- Validation paiement initial (admin): `/admin/users` (action `Paiement + crédits`, valeurs par défaut: `50` invitations, `1` crédit création événement).
- Demandes d'augmentation (client): `/dashboard`.
- Validation des demandes (admin): `/admin/users`.

### Migration manuelle (si necessaire)

```bash
php scripts/migrate_credit_system.php
```

## Communications multicanal

- Page campagne: `/communications`
- Canaux disponibles: `email`, `sms`, `whatsapp`, `manual`
- En mode `manual`, le système génère un message + lien à copier/coller.

### Variables d'environnement messaging

Selection des providers:

```bash
SMS_PROVIDER=twilio
WHATSAPP_PROVIDER=meta
DEFAULT_PHONE_COUNTRY_CODE=+242
```

SMS via Twilio:

```bash
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_SMS_FROM=+12345678900
```

WhatsApp via Twilio:

```bash
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
```

SMS via Infobip:

```bash
SMS_PROVIDER=infobip
INFOBIP_BASE_URL=https://<votre-sous-domaine>.api.infobip.com
INFOBIP_API_KEY=<API_KEY>
INFOBIP_SMS_FROM=InviteQR
DEFAULT_PHONE_COUNTRY_CODE=+242
```

WhatsApp via Meta Cloud API:

```bash
WHATSAPP_PROVIDER=meta
WHATSAPP_META_PHONE_NUMBER_ID=<PHONE_NUMBER_ID>
WHATSAPP_META_ACCESS_TOKEN=<PERMANENT_ACCESS_TOKEN>
WHATSAPP_META_GRAPH_VERSION=v20.0
```

Note:
- En production WhatsApp, Meta peut exiger un template approuve hors fenetre de conversation 24h.

### Test SMTP en CLI

```bash
php scripts/test_mailer.php --to="destinataire@email.com" --name="Nom Test"
```


