# qrGlossaire

## Acces administrateur principal

- URL login admin: `/admin/login`
- URL dashboard admin: `/admin/dashboard`
- URL login client (organisateur): `/login`

Le compte admin principal est stocke dans la table `admins` (separee de `users`).

### Creer le premier admin

Executer en ligne de commande:

```bash
php scripts/create_admin.php --email="admin@email.com" --password="MotDePasseFort123!" --name="Admin Principal"
```

## Credits invitations et demandes

- Prix unitaire: `$0.30` par invitation.
- Validation paiement initial (admin): `/admin/users` (action `Paiement + credits`, valeurs par defaut: `50` invitations, `1` credit creation evenement).
- Demandes d augmentation (client): `/dashboard`.
- Validation des demandes (admin): `/admin/users`.

### Migration manuelle (si necessaire)

```bash
php scripts/migrate_credit_system.php
```

## Communications multicanal

- Page campagne: `/communications`
- Canaux disponibles: `email`, `sms`, `whatsapp`, `manual`
- En mode `manual`, le systeme genere un message + lien a copier/coller.

### Variables d environnement (SMS/WhatsApp via Twilio)

```bash
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_SMS_FROM=+12345678900
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
DEFAULT_PHONE_COUNTRY_CODE=+242
```

### Test SMTP en CLI

```bash
php scripts/test_mailer.php --to="destinataire@email.com" --name="Nom Test"
```

