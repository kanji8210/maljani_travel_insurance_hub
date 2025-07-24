# Templates Directory

## ⚠️ Important - Architecture Clarification

### **Système de Formulaire de Vente**

**✅ Système Principal (Utilisé):**
- **Fichier :** `includes/class-maljani-sales-page.php`
- **Usage :** Shortcode `[maljani_policy_sale]`
- **Fonctionnalités :**
  - Processus en 4 étapes : Dates → Région → Police → Formulaire complet
  - Filtrage par région avec tax_query
  - Gestion avancée des paramètres URL
  - Auto-soumission des formulaires
  - Validation côté client et serveur

**❌ Ancien Template (Obsolète):**
- **Fichier :** `templates/sales-form.php`
- **État :** Remplacé par une page de redirection
- **Raison :** Éviter les conflits entre les deux systèmes

## 🚀 Configuration Recommandée

### Étape 1 : Créer une Page de Vente
```bash
1. WordPress Admin > Pages > Ajouter une nouvelle
2. Titre : "Acheter une Police" (ou similaire)
3. Contenu : [maljani_policy_sale]
4. Publier la page
```

### Étape 2 : Configurer dans le Plugin
```bash
1. Admin > Maljani Travel > Settings
2. Policy Sale Page : Sélectionner votre page créée
3. Sauvegarder
```

### Étape 3 : Vérifier la Configuration
```bash
Admin > Maljani Travel > Diagnostic
```

## 📁 Fichiers CSS/JS

- **`sales-form.css`** : Styles pour les formulaires (chargé automatiquement)
- **`sales-form.js`** : Scripts JavaScript pour validation et interactions
- **`diagnostic.php`** : Outil de diagnostic de configuration

## 🔗 URLs de Test

Une fois configuré, testez avec :
```
votre-site.com/acheter-police/?departure=2024-12-01&return=2024-12-10
```

## 🆘 Résolution de Problèmes

**Problème : Double formulaire**
- ✅ Solution : L'ancien template est maintenant désactivé

**Problème : Formulaire ne s'affiche pas**
- Vérifiez que le shortcode `[maljani_policy_sale]` est présent
- Vérifiez la configuration dans Settings
- Utilisez l'outil Diagnostic