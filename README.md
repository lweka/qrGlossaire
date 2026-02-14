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

