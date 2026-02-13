# Rapport de Résolution des Problèmes - Sprint 1 Pop Quiz

## Chapitre 1: Backend

### ⚠️ Gestion des Erreurs

#### Problème Identifié

La page `http://localhost:8888/broken` retourne une erreur HTTP 500 sans message d'indication visible.

#### Analyse de l'Erreur

- **Cause racine** : Faute de frappe dans `backend/index.php` au niveau du gestionnaire de route `/broken` : `$response->getBody()->wr​ite("Hello world!");` contient un caractère invisible (espace de largeur nulle, U+200B) entre `wr` et `ite`, rendant la méthode invalide.
- **Comportement** : Erreur fatale PHP ("Call to undefined method"), mais `display_errors` est désactivé, et les erreurs sont loggées dans `/tmp/sprint1-php-error.log`.
- **Vérification** : Consultez le log pour l'erreur exacte.

#### Solution Proposée

Corrigez la méthode en supprimant le caractère invisible.

```php
// filepath: /Users/void/dev/sprint1-pop-quiz/backend/index.php
$app->get('/broken', function (Request $request, Response $response, $args) {
    /** @disregard P1013 because we're just testing */
    $response->getBody()->write("Hello world!");
    return $response;
});
```
