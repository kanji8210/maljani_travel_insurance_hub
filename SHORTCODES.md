# 📚 Maljani Travel Insurance Hub - Shortcodes Documentation

## 🚀 Plugin Overview

**Maljani Travel Insurance Hub** est un plugin WordPress modulaire conçu pour simplifier la gestion des assureurs et des polices d'assurance voyage dans une interface d'administration centralisée.

### ✨ Fonctionnalités Principales
- **Gestion des assureurs** : Custom Post Type pour les profils d'assureurs
- **Gestion des polices** : Custom Post Type pour les polices d'assurance
- **Système de vente** : Formulaires de vente avec calcul automatique des primes
- **Génération PDF** : Documents de police et lettres pour ambassades avec QR codes
- **Dashboard utilisateur** : Interface pour agents et clients
- **Système de rôles** : Rôles Agent et Insurer

---

## 🎯 Shortcodes Disponibles

### 1. `[maljani_policy_sale]`
**Formulaire de vente de polices d'assurance**

#### Description
Affiche un formulaire complet pour la vente de polices d'assurance voyage avec calcul automatique des primes.

#### Usage
```
[maljani_policy_sale]
```

#### Fonctionnalités
- ✅ Sélection de police d'assurance
- ✅ Calcul automatique des primes selon les jours
- ✅ Validation des données client
- ✅ Intégration avec les systèmes de paiement
- ✅ Génération automatique de numéros de police

#### Attributs (optionnels)
```
[maljani_policy_sale policy_id="123" destination="Europe"]
```

**Attributs disponibles :**
- `policy_id` : ID de la police à présélectionner
- `destination` : Destination à présélectionner
- `agent_id` : ID de l'agent responsable

#### Exemple d'utilisation
```
<!-- Formulaire général -->
[maljani_policy_sale]

<!-- Formulaire avec police présélectionnée -->
[maljani_policy_sale policy_id="45"]

<!-- Formulaire pour un agent spécifique -->
[maljani_policy_sale agent_id="12"]
```

---

### 2. `[maljani_sales_form]`
**Alias du formulaire de vente (rétrocompatibilité)**

#### Description
Identique à `[maljani_policy_sale]` - maintenu pour la rétrocompatibilité.

#### Usage
```
[maljani_sales_form]
```

---

### 3. `[maljani_user_dashboard]`
**Dashboard utilisateur pour agents et clients**

#### Description
Interface de dashboard permettant aux utilisateurs de gérer leurs polices, voir l'historique des ventes et accéder aux documents.

#### Usage
```
[maljani_user_dashboard]
```

#### Fonctionnalités
- ✅ Historique des polices
- ✅ Téléchargement des documents PDF
- ✅ Statut des paiements
- ✅ Gestion du profil utilisateur
- ✅ Interface différenciée selon le rôle (Agent/Client)

#### Interface selon les rôles

**Pour les Agents :**
- Vue de toutes leurs ventes
- Statistiques de performance
- Gestion des clients
- Génération de rapports

**Pour les Clients :**
- Leurs polices actives
- Historique des achats
- Documents téléchargeables
- Informations de contact

#### Exemple d'utilisation
```
[maljani_user_dashboard]
```

---

### 4. `[maljani_agent_register]`
**Formulaire d'inscription pour nouveaux agents**

#### Description
Formulaire d'inscription permettant aux nouveaux agents de créer un compte avec le rôle approprié.

#### Usage
```
[maljani_agent_register]
```

#### Fonctionnalités
- ✅ Création de compte agent
- ✅ Validation des informations professionnelles
- ✅ Assignation automatique du rôle "Agent"
- ✅ Email de confirmation
- ✅ Intégration avec le système d'authentification WordPress

#### Champs du formulaire
- **Informations personnelles :** Nom, prénom, email
- **Informations professionnelles :** Société, numéro de licence
- **Coordonnées :** Téléphone, adresse
- **Authentification :** Nom d'utilisateur, mot de passe

#### Exemple d'utilisation
```
[maljani_agent_register]
```

---

### 5. `[maljani_icon]`
**Affichage d'icônes avec options de style**

#### Description
Shortcode polyvalent pour afficher des icônes avec du texte, des liens et différentes options de style. Supporte Dashicons WordPress, FontAwesome et icônes personnalisées.

#### Usage
```
[maljani_icon name="star-filled"]
```

#### Attributs disponibles
- `name` : Nom de l'icône (requis)
- `size` : Taille (small, medium, large, xl) - défaut: medium
- `color` : Couleur CSS (hex, nom, rgb) - optionnel
- `text` : Texte à afficher à côté de l'icône - optionnel
- `link` : URL de lien - optionnel
- `class` : Classes CSS additionnelles - optionnel
- `style` : Type d'icône (dashicons, fontawesome, custom) - défaut: dashicons

#### Exemples d'utilisation

**Icône simple :**
```
[maljani_icon name="shield"]
```

**Icône avec texte :**
```
[maljani_icon name="phone" text="Contactez-nous"]
```

**Icône avec lien :**
```
[maljani_icon name="email" text="Envoyer un email" link="mailto:contact@example.com"]
```

**Icône personnalisée :**
```
[maljani_icon name="insurance-policy" size="large" color="#0073aa" text="Votre Police"]
```

**FontAwesome (si chargé) :**
```
[maljani_icon name="plane" style="fontawesome" text="Voyage"]
```

#### Icônes Dashicons populaires
- `star-filled`, `star-empty` : Étoiles
- `shield`, `shield-alt` : Protection/Sécurité
- `phone`, `email`, `location` : Contact
- `yes`, `no`, `warning` : Statuts
- `cart`, `money` : Commerce
- `clock`, `calendar` : Temps
- `search`, `analytics` : Outils
- `thumbs-up`, `thumbs-down` : Approbation

#### Tailles disponibles
- `small` : 16px
- `medium` : 20px (défaut)
- `large` : 24px
- `xl` : 32px

#### Classes CSS générées
```css
.maljani-icon { /* Icône de base */ }
.maljani-icon-wrapper { /* Conteneur icône + texte */ }
.maljani-icon-link { /* Lien contenant l'icône */ }
.size-small, .size-medium, .size-large, .size-xl { /* Tailles */ }
```

---

## 🛠️ Configuration et Installation

### Étapes d'installation
1. **Télécharger et activer** le plugin
2. **Configurer les pages** dans les réglages
3. **Créer les polices** d'assurance
4. **Ajouter les shortcodes** aux pages appropriées

### Configuration recommandée

#### Pages à créer
```
/buy-insurance/          → [maljani_policy_sale]
/agent-dashboard/        → [maljani_user_dashboard]
/become-agent/          → [maljani_agent_register]
/my-policies/           → [maljani_user_dashboard]
```

#### Structure recommandée
```
📄 Accueil
├── 🛒 Acheter une assurance → [maljani_policy_sale]
├── 👤 Espace Agent → [maljani_user_dashboard]
├── 📋 Devenir Agent → [maljani_agent_register]
└── 📄 Mes Polices → [maljani_user_dashboard]
```

---

## 🎨 Personnalisation

### CSS Classes disponibles

#### Formulaire de vente
```css
.maljani-sales-form {}
.maljani-policy-selector {}
.maljani-premium-display {}
.maljani-client-info {}
.maljani-payment-section {}
```

#### Dashboard utilisateur
```css
.maljani-dashboard {}
.maljani-user-stats {}
.maljani-policy-list {}
.maljani-document-links {}
```

#### Formulaire d'inscription
```css
.maljani-registration-form {}
.maljani-agent-fields {}
.maljani-validation-messages {}
```

### Hooks disponibles
```php
// Actions
do_action('maljani_before_policy_sale', $policy_id);
do_action('maljani_after_policy_sale', $sale_id);
do_action('maljani_agent_registered', $user_id);

// Filtres
apply_filters('maljani_premium_calculation', $premium, $days, $policy_id);
apply_filters('maljani_pdf_template', $template, $type);
```

---

## 📱 Responsive Design

Tous les shortcodes sont **100% responsive** et s'adaptent automatiquement :
- 📱 **Mobile** : Interface optimisée tactile
- 💻 **Tablet** : Mise en page adaptative
- 🖥️ **Desktop** : Interface complète

---

## 🔧 Paramètres Avancés

### Variables d'environnement
```php
// Dans wp-config.php
define('MALJANI_PDF_LOGO_PATH', '/custom/logo/path/');
define('MALJANI_EMAIL_FROM', 'noreply@votresite.com');
define('MALJANI_DEFAULT_CURRENCY', 'EUR');
```

### Options de base de données
```php
// Récupérer les options
$sales_page = get_option('maljani_sales_page');
$dashboard_page = get_option('maljani_user_dashboard_page');
$agent_page = get_option('maljani_user_registration_page');
```

---

## 📄 Génération PDF

### Documents générés
1. **Police d'assurance** : Document contractuel complet
2. **Lettre pour ambassade** : Avec QR code de vérification
3. **Reçu de paiement** : Justificatif de transaction

### Fonctionnalités PDF
- ✅ **QR Code de vérification** : Sécurité anti-contrefaçon
- ✅ **Logos personnalisés** : Site et assureur
- ✅ **Mise en page moderne** : Design professionnel
- ✅ **Multilangue** : Support français/anglais

### URL de génération
```
/wp-content/plugins/maljani_travel_insurance_hub/includes/generate-policy-pdf.php?sale_id=123
```

---

## 🔐 Sécurité

### Fonctionnalités de sécurité
- ✅ **Nonces WordPress** : Protection CSRF
- ✅ **Sanitisation des données** : Validation des entrées
- ✅ **Vérification des capacités** : Contrôle d'accès
- ✅ **Hash de vérification** : SHA256 pour les documents

### Rôles et permissions
```php
// Rôle Agent
'read' => true,
'edit_policies' => true,
'view_sales' => true,

// Rôle Insurer
'read' => true,
'manage_policies' => true,
'view_reports' => true,
```

---

## 🚨 Dépannage

### Problèmes courants

#### Shortcode ne s'affiche pas
```
✅ Vérifier que le plugin est activé
✅ Vérifier la syntaxe du shortcode
✅ Consulter les logs d'erreur WordPress
```

#### Calcul de prime incorrect
```
✅ Vérifier la configuration des tranches de prix
✅ Tester avec des dates valides
✅ Vérifier les métadonnées des polices
```

#### PDF ne se génère pas
```
✅ Vérifier les permissions de fichiers
✅ Tester la librairie TCPDF
✅ Vérifier les chemins des logos
```

### Diagnostic automatique
Utilisez la page de diagnostic intégrée :
```
/wp-admin/admin.php?page=maljani-diagnostic
```

---

## 📞 Support

### Ressources
- **GitHub** : [Maljani Travel Insurance Hub](https://github.com/kanji8210/maljani_travel_insurance_hub)
- **Auteur** : Dennis Kip
- **Website** : [kipdevwp.tech](https://kipdevwp.tech/)

### Version
- **Version actuelle** : 1.0.0
- **Compatibilité WordPress** : 5.0+
- **Compatibilité PHP** : 7.4+

---

## 🎯 Exemples Complets

### Site d'assurance voyage complet
```html
<!-- Page d'accueil -->
<h1>Assurance Voyage Maljani</h1>
<p>Protégez vos voyages avec nos assurances complètes.</p>
[maljani_policy_sale]

<!-- Page agent -->
<h1>Espace Agent</h1>
<p>Gérez vos ventes et clients</p>
[maljani_user_dashboard]

<!-- Page inscription agent -->
<h1>Devenir Partenaire</h1>
<p>Rejoignez notre réseau d'agents</p>
[maljani_agent_register]
```

### Intégration avec thème
```php
// Dans functions.php de votre thème
function custom_maljani_styling() {
    if (has_shortcode(get_post()->post_content, 'maljani_policy_sale')) {
        wp_enqueue_style('custom-maljani', get_template_directory_uri() . '/maljani-custom.css');
    }
}
add_action('wp_enqueue_scripts', 'custom_maljani_styling');
```

---

*Documentation mise à jour le 28 juillet 2025 - Version 1.0.0*
