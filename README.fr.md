# Sylius Company Insights Plugin

Tableau de bord des achats pour les comptes entreprise B2B de [Sylius](https://sylius.com) 2. L'administrateur d'une entreprise voit, dans l'espace client, combien son entreprise dépense et à quel rythme elle commande : dépense, nombre de commandes, panier moyen et acheteurs actifs de la période choisie, chacun avec sa variation par rapport à la période précédente de même durée, et les commandes en attente d'approbation à part.

Construit sur [`tmonier/sylius-company-accounts-plugin`](https://github.com/tmonier/sylius-company-accounts-plugin) (entreprises, membres, rôles, commandes de l'entreprise). [English](README.md) | Français

## Ce qu'il fait

- **Page** `/{_locale}/account/company/insights`, accessible par l'onglet « Tableau de bord des achats » de l'espace « Mon entreprise ».
- **Indicateurs** de la période, avec la variation en pourcentage par rapport à la période précédente de même durée :
  - dépense HT ;
  - nombre de commandes ;
  - panier moyen HT ;
  - acheteurs actifs (clients ayant passé au moins une commande) ;
  - commandes en attente d'approbation (nombre et montant), affichées à part et non comptées dans la dépense.
- **Périodes** : 30 derniers jours, trimestre en cours, 12 derniers mois (par défaut), année en cours. La période figure dans le titre et dans l'URL (`?period=current_quarter`). Le trimestre et l'année en cours sont comparés aux mêmes jours écoulés du trimestre et de l'année précédents.
- **Accès** : administrateurs de l'entreprise uniquement (`COMPANY_VIEW_INSIGHTS`). Les acheteurs et les approbateurs reçoivent un 403 expliqué et n'ont pas l'onglet.
- **Cloisonnement entre entreprises** : l'entreprise est celle du membre connecté, jamais un paramètre de la requête. Le seul paramètre est la période, tirée d'une liste blanche ; toute autre valeur est remplacée par la période par défaut.
- **Commandes comptées** : checkout terminé, non annulées, rattachées à l'entreprise par `CompanyOrder`, non en attente d'approbation.
- **Montants** hors taxes, dans la devise de la commande. Avec plusieurs devises, il y a une ligne par devise et rien n'est converti.
- Les agrégats sont calculés par la base de données (aucune entité chargée) en SQL standard : MySQL, MariaDB et PostgreSQL.
- Libellés en français et en anglais. La page ne repose pas sur la couleur seule (flèches et phrases) et évite les classes de texte peu contrastées.

## Installation

```bash
composer require tmonier/sylius-company-insights-plugin
```

1. Déclarer le bundle (après `TmonierSyliusCompanyAccountsPlugin`) dans `config/bundles.php` :

    ```php
    Tmonier\SyliusCompanyInsightsPlugin\TmonierSyliusCompanyInsightsPlugin::class => ['all' => true],
    ```

2. Importer la configuration dans `config/packages/_sylius.yaml` :

    ```yaml
    imports:
        - { resource: "@TmonierSyliusCompanyInsightsPlugin/config/config.yaml" }
    ```

3. Importer les routes dans `config/routes.yaml` :

    ```yaml
    tmonier_sylius_company_insights:
        resource: "@TmonierSyliusCompanyInsightsPlugin/config/routes.yaml"
    ```

4. Exécuter la migration (un index sur `tmonier_company_order`, déclaré dans le mapping Doctrine pour que les diffs de schéma le conservent) :

    ```bash
    bin/console doctrine:migrations:migrate
    ```

Le contrôle d'accès de la boutique Sylius doit protéger `^/(?:[a-z]{2,4}(?:_[A-Za-z]{2})?)/account` (c'est le cas dans la configuration Sylius par défaut).

## Permission

| Attribut | Sujet | Accordée à |
|----------|-------|------------|
| `COMPANY_VIEW_INSIGHTS` | entreprise | un membre actif au rôle `admin` de l'entreprise active, pour cette entreprise seulement |

```twig
{% if is_granted('COMPANY_VIEW_INSIGHTS', company) %}...{% endif %}
```

## Commandes en attente d'approbation

Avec [`tmonier/sylius-purchase-approval-plugin`](https://github.com/tmonier/sylius-purchase-approval-plugin) installé, les commandes qu'il retient apparaissent dans leur propre bloc et ne comptent pas dans la dépense tant qu'un approbateur ne les a pas approuvées. Sans lui, le bloc indique qu'aucune commande n'est en attente. Il n'y a pas de dépendance dure : l'intégration est un port (`PendingOrderConditionInterface`) que l'on peut implémenter pour un autre circuit d'approbation en redéfinissant le service `tmonier_company_insights.pending_order_condition`.

## Versions supportées (PHP, Symfony, Sylius) et impact de version

| | Supporté |
|-|----------|
| PHP | 8.2, 8.3, 8.4 |
| Symfony | 6.4, 7.4, 8.0 |
| Sylius | 2.2 (`~2.2.0`), 2.3 (`~2.3.0`) |
| Base de données | MySQL 8.4, MariaDB 11.4, PostgreSQL 17 |
| Dépendance | `tmonier/sylius-company-accounts-plugin` ^1.1 |

La matrice de CI est celle de `tmonier/sylius-company-accounts-plugin` et de `tmonier/sylius-agentic-feeds-plugin` : Sylius 2.2 sur PHP 8.2 à 8.4 avec Symfony 6.4 et 7.4, Sylius 2.3 sur PHP 8.3 et 8.4 avec Symfony 6.4, 7.4 et 8.0, plus des cellules MariaDB et PostgreSQL. PHP 8.5 n'a pas encore de cellule, aucun plugin compagnon n'en ayant. Impact de version : aucun code du plugin ne dépend d'une API propre à une version ; le voter garde la signature `mixed $vote = null` valable de Symfony 6.4 à 8.0 et le SQL est portable.

## Développement

```bash
composer install
vendor/bin/ecs check
vendor/bin/phpstan analyse            # niveau max
vendor/bin/rector process --dry-run
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # nécessite la base de test, voir le Makefile
```

Les décisions de conception sont consignées dans [`docs/adr`](docs/adr).

## Licence

MIT
