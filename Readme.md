To run project:

1. Clone to local dir, eg. /srv/app
2. Upload to the server with reachable IP address from internet with SSL (https enabled)
3. Run docker compose: docker compose up -d
4. Set TG webhook at: https://\<domain\>/telegram/set-webhook
5. Swagger available at: http://\<domain\>/api/documentation
6. Add TG bot @laravel_373_bot
7. Send a command /start or /stop to subscribe or unsubscribe from notifications
8. Run notify command: sh ./command.sh
