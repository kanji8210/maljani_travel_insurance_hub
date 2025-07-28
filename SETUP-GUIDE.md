# Maljani Travel Insurance Hub - Guide de Configuration

## 🚀 Configuration Rapide

### 1. Activation du Plugin
1. Allez dans **Extensions** > **Extensions installées**
2. Trouvez "Maljani Travel Insurance Hub"
3. Cliquez sur **Activer**

### 2. Configuration Initiale
Après activation, accédez à **Maljani** dans le menu administrateur WordPress.

#### A. Configuration des Assureurs
1. Allez dans **Maljani** > **Profils d'Assureurs**
2. Cliquez sur **Ajouter un nouveau**
3. Remplissez les informations :
   - Nom de l'assureur
   - Logo (recommandé : 200x100px)
   - Informations de contact
   - Détails de couverture

#### B. Configuration des Polices
1. Allez dans **Maljani** > **Polices d'Assurance**
2. Définissez les types de couverture
3. Configurez les tarifs par âge et durée

### 3. Pages Nécessaires

#### Page de Vente (Obligatoire)
```
Titre : Assurance Voyage
Contenu : [maljani_policy_sale]
```

#### Tableau de Bord Utilisateur
```
Titre : Mon Tableau de Bord
Contenu : [maljani_user_dashboard]
```

#### Inscription Agent
```
Titre : Devenir Agent
Contenu : [maljani_agent_register]
```

### 4. Configuration des Rôles

#### Pour les Agents
1. Allez dans **Utilisateurs** > **Tous les utilisateurs**
2. Modifiez l'utilisateur
3. Changez le rôle vers "Agent Maljani"

#### Permissions Automatiques
- **Agents** : Vente de polices, gestion des clients
- **Administrateurs** : Accès complet au système

### 5. Paramètres Avancés

#### Configuration PDF
Les PDFs sont générés automatiquement avec :
- QR codes de vérification
- Design moderne
- Informations complètes de la police

#### Codes de Vérification
Chaque document généré inclut un code QR unique pour vérification d'authenticité.

## 🔧 Configuration Technique

### Prérequis
- WordPress 5.0+
- PHP 7.4+
- Extension GD (pour QR codes)
- Permissions d'écriture dans `/wp-content/uploads/`

### Structure des Données
Le plugin crée automatiquement :
- Types de contenu personnalisés
- Tables de base de données
- Rôles utilisateur

### Sauvegardes
Recommandé avant installation :
```bash
# Base de données
mysqldump -u username -p database_name > backup.sql

# Fichiers
tar -czf wordpress-backup.tar.gz /path/to/wordpress/
```

## 🎨 Personnalisation

### CSS Personnalisé
Ajoutez dans votre thème :
```css
/* Formulaire de vente */
.maljani-policy-form {
    max-width: 800px;
    margin: 0 auto;
}

/* Tableau de bord */
.maljani-dashboard {
    padding: 20px;
    background: #f9f9f9;
}
```

### Hooks Disponibles
```php
// Action après création de police
do_action('maljani_policy_created', $policy_id);

// Filtre pour modification de tarifs
apply_filters('maljani_premium_calculation', $amount, $age, $duration);
```

## 📞 Support et Dépannage

### Problèmes Courants

#### Les PDFs ne se génèrent pas
1. Vérifiez les permissions du dossier uploads
2. Assurez-vous que l'extension GD est activée
3. Contactez votre hébergeur si nécessaire

#### Les QR codes n'apparaissent pas
1. Vérifiez l'extension GD PHP
2. Testez avec un autre navigateur
3. Vérifiez les logs d'erreur WordPress

#### Shortcodes non fonctionnels
1. Vérifiez que le plugin est activé
2. Utilisez exactement la syntaxe documentée
3. Vérifiez les conflits avec d'autres plugins

### Logs de Débogage
Activez le débogage WordPress :
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Contacts
- Support technique : [votre-email@domain.com]
- Documentation : DOCUMENTATION.md
- Référence shortcodes : SHORTCODES.md

---

*Guide de configuration - Version 1.0.0 - Juillet 2025*
