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

