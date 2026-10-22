# Sylius Company Insights Plugin

Tableau de bord des achats pour les comptes entreprise B2B de [Sylius](https://sylius.com) 2. L'administrateur d'une entreprise voit, dans l'espace client, combien son entreprise dépense et à quel rythme elle commande : dépense, nombre de commandes, panier moyen et acheteurs actifs de la période choisie, chacun avec sa variation par rapport à la période précédente de même durée, et les commandes en attente d'approbation à part.

Construit sur [`tmonier/sylius-company-accounts-plugin`](https://github.com/thibmonier/sylius-company-accounts-plugin) (entreprises, membres, rôles, commandes de l'entreprise). [English](README.md) | Français

## Ce qu'il fait

- **Page** `/{_locale}/account/company/insights`, accessible par l'onglet « Tableau de bord des achats » de l'espace « Mon entreprise ».
- **Indicateurs** de la période, avec la variation en pourcentage par rapport à la période précédente de même durée :
  - dépense HT ;
  - nombre de commandes ;
  - panier moyen HT ;
  - acheteurs actifs (clients ayant passé au moins une commande) ;
  - commandes en attente d'approbation (nombre et montant), affichées à part et non comptées dans la dépense.
- **Périodes** : 30 derniers jours, trimestre en cours, 12 derniers mois (par défaut : le mois civil en cours et les 11 précédents), année en cours. La période figure dans le titre et dans l'URL (`?period=current_quarter`). Le trimestre et l'année en cours sont comparés aux mêmes jours écoulés du trimestre et de l'année précédents.
- **Achats par mois** : graphique en barres de la dépense des 12 derniers mois à côté des mêmes mois de l'année précédente. Une bascule (`?metric=amount|orders`, liste blanche) affiche le nombre de commandes au lieu du montant.
  - Les mois sans commande sont à zéro, jamais des trous ; le mois en cours est marqué « en cours » et n'est pas un mois complet.
  - Les mois suivent un fuseau horaire : celui de PHP par défaut, ou le paramètre `tmonier_company_insights.timezone` (par exemple `Europe/Paris` ; les canaux Sylius n'ont pas de fuseau propre). Une commande passée le 31 à 23 h 30 compte dans le bon mois, heure d'été comprise.
  - Une série par devise, aucune conversion et aucun total qui mélange les devises.
  - Les 12 barres totalisent la dépense de l'indicateur « 12 derniers mois » (mêmes commandes, mêmes règles).
  - Accessibilité : un tableau de données (mois, dépense, commandes, mêmes chiffres de l'année précédente, en-têtes `th scope`) est placé sous le graphique et c'est lui que lisent les lecteurs d'écran ; le dessin est `aria-hidden`. Les séries se distinguent par le remplissage (plein / hachuré) et la légende, pas par la couleur seule ; les couleurs ont un contraste d'au moins 7:1 sur fond blanc.
  - Pas de CDN ni de script en ligne : les données sont dans des attributs `data-*` lus par un contrôleur Stimulus (voir [ADR-0001](docs/adr/0001-charts-library.md)). Sans JavaScript, le tableau reste.
- **Classements** de la période choisie, sous le graphique, un jeu par devise, chacun limité à 10 lignes avec un lien « Voir tout » vers sa propre page paginée (`/account/company/insights/{buyers|products|categories}`, 25 lignes par page, `?page=`, `?currency=`) :
  - **Top acheteurs** : membre, dépense HT, nombre de commandes et part de la dépense. Un membre désactivé depuis ses commandes reste dans la liste avec la mention « désactivé ».
  - **Top produits** (variantes) : code et nom, quantité, dépense HT et date du dernier achat. Classés par dépense par défaut, par quantité avec `?sort=quantity` (liste blanche). Le nom est celui de la langue de la page ; sans traduction, le code est affiché.
  - **Top catégories** (catégorie principale du produit) : dépense et part, avec un anneau et son tableau équivalent. Toutes les catégories sont lues, donc les parts font 100 % (plus fort reste) ; la ligne « autres catégories » complète une liste courte et les produits sans catégorie forment une ligne à part.
  - Mêmes commandes que les indicateurs. Une ligne compte pour son montant HT après remises de ligne (les acheteurs utilisent le total HT de la commande, donc leur total est la dépense de l'indicateur). Une devise n'est jamais additionnée à une autre.
  - Accessibilité : chaque classement est un tableau avec légende et en-têtes `th scope` ; l'anneau est `aria-hidden`, ses parts se distinguent par des motifs de remplissage et la légende (avec la part), pas par la couleur seule, et les couleurs ont un contraste d'au moins 7:1 sur blanc.
  - **Données personnelles** : seuls le prénom et le nom d'un membre sont lus et affichés. Aucune adresse e-mail, aucun téléphone ni adresse n'est sélectionné par les requêtes ni présent dans le HTML. L'entreprise est celle du membre connecté ; le classement (`buyers|products|categories`), le tri, la page et la devise sont contrôlés par liste blanche ou motif strict, et toute autre valeur donne la valeur par défaut ou un 404.
- **Accès** : administrateurs de l'entreprise uniquement (`COMPANY_VIEW_INSIGHTS`). Les acheteurs et les approbateurs reçoivent un 403 expliqué et n'ont pas l'onglet.
- **Cloisonnement entre entreprises** : l'entreprise est celle du membre connecté, jamais un paramètre de la requête. Le seul paramètre est la période, tirée d'une liste blanche ; toute autre valeur est remplacée par la période par défaut.
- **Commandes comptées** : checkout terminé, non annulées, rattachées à l'entreprise par `CompanyOrder`, non en attente d'approbation.
- **Montants** hors taxes, dans la devise de la commande. Avec plusieurs devises, il y a une ligne par devise et rien n'est converti.
- Les agrégats sont calculés par la base de données (aucune entité chargée) en SQL standard : MySQL, MariaDB et PostgreSQL.
- Libellés en français et en anglais. La page ne repose pas sur la couleur seule (flèches et phrases) et évite les classes de texte peu contrastées.

## Captures d'écran

Données de démonstration de [sylius-b2b-demo](https://github.com/thibmonier/sylius-b2b-demo), connecté en administratrice de l'entreprise.

![Indicateurs des 12 derniers mois et achats par mois face à l'année précédente](docs/images/overview.fr.jpg)

![Top acheteurs avec leur dépense, leurs commandes et leur part de la dépense, membre désactivé compris](docs/images/buyers.fr.jpg)

![Top catégories : anneau à motifs et tableau équivalent](docs/images/categories.fr.jpg)

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

5. Construire le graphique (facultatif, les tableaux fonctionnent sans). Ajoutez la dépendance `apexcharts` (déjà utilisée par l'administration Sylius) et importez le point d'entrée du plugin dans `assets/shop/entrypoint.js` de votre boutique :

    ```js
    import '@vendor/tmonier/sylius-company-insights-plugin/assets/shop/entrypoint';
    ```

Le fuseau horaire des mois se règle par un paramètre (par défaut : celui de PHP) :

```yaml
parameters:
    tmonier_company_insights.timezone: 'Europe/Paris'
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

Avec [`tmonier/sylius-purchase-approval-plugin`](https://github.com/thibmonier/sylius-purchase-approval-plugin) installé, les commandes qu'il retient apparaissent dans leur propre bloc et ne comptent pas dans la dépense tant qu'un approbateur ne les a pas approuvées. Sans lui, le bloc indique qu'aucune commande n'est en attente. Il n'y a pas de dépendance dure : l'intégration est un port (`PendingOrderConditionInterface`) que l'on peut implémenter pour un autre circuit d'approbation en redéfinissant le service `tmonier_company_insights.pending_order_condition`.

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
