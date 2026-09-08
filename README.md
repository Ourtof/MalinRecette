# MalinRecette — Backend Symfony

API Symfony du projet MalinRecette.

dans le dépôt Git. Ils sont définis dans un .env.local et transférés par FileZilla directement sur le serveur.

## CI/CD

Pipeline GitHub sur la branche dev :

- Service MySQL démarré pour les tests.
- Exécution des tests.

## DEPLOIEMENT

- Passer APP_ENV=prod dans le fichier .env.
- composer install --no-dev --optimize-autoloader
- Transférer les fichiers vers le serveur via FileZilla.
- php bin/console doctrine:migrations:migrate --env=prod
- php bin/console cache:clear --env=prod

## EN PROD

- Remplacer allow_origin par une variable d'environnement dans le nelmio_cors.
- Faire passer tous les tests.
- Améliorer le pipeline : automatiser l'envoi par FTP vers l'hébergement.