# 📖 Maljani Travel Insurance Hub - Documentation Index

## 📚 Available Documentation

### 🚀 [QUICK-REFERENCE.md](QUICK-REFERENCE.md)
**Quick reference guide for shortcodes and basic setup**
- All available shortcodes
- Quick setup instructions
- Basic troubleshooting

### 📚 [SHORTCODES.md](SHORTCODES.md)
**Complete shortcode documentation with examples**
- Detailed shortcode descriptions
- Configuration options
- Advanced usage examples
- CSS customization
- Security features

### 📄 [README.txt](README.txt)
**WordPress plugin repository format documentation**
- Plugin overview
- Installation instructions
- FAQ section
- Changelog

### 🔧 [MIGRATION-NOTES.md](MIGRATION-NOTES.md)
**Technical migration and development notes**
- Database structure
- File architecture
- Development guidelines

### 📋 [templates/README.md](templates/README.md)
**Template system documentation**
- Template hierarchy
- Customization options
- Template files description

## 🎯 Shortcodes Quick Reference

| Shortcode | Purpose | Documentation | Style Protection |
|-----------|---------|---------------|------------------|
| `[maljani_policy_sale]` | Insurance sales form | [SHORTCODES.md#1-maljani_policy_sale](SHORTCODES.md#1-maljani_policy_sale) | ✅ Isolated |
| `[maljani_user_dashboard]` | User dashboard | [SHORTCODES.md#3-maljani_user_dashboard](SHORTCODES.md#3-maljani_user_dashboard) | ✅ Isolated |
| `[maljani_agent_register]` | Agent registration | [SHORTCODES.md#4-maljani_agent_register](SHORTCODES.md#4-maljani_agent_register) | ✅ Isolated |
| `[maljani_icon]` | Icon display with styling | [SHORTCODES.md#5-maljani_icon](SHORTCODES.md#5-maljani_icon) | ✅ Isolated |
| `[maljani_sales_form]` | Legacy sales form | [SHORTCODES.md#2-maljani_sales_form](SHORTCODES.md#2-maljani_sales_form) | ✅ Isolated |

## 🛡️ Style Isolation System

Le plugin utilise un système d'isolation CSS avancé pour garantir que les styles ne sont pas affectés par les thèmes WordPress :

### Fonctionnalités d'Isolation
- **Reset CSS complet** avec spécificité renforcée
- **Conteneur d'isolation** `.maljani-plugin-container`
- **Styles protégés** avec `!important` ciblé
- **CSS critique inline** pour rendu rapide
- **Compatibilité thème** testée avec thèmes populaires

### Avantages
- ✅ Apparence consistante sur tous les thèmes
- ✅ Pas de conflits avec styles de thème
- ✅ Performance optimisée
- ✅ Responsive design garanti
- ✅ Maintenance simplifiée

### Documentation Complète
Consultez [STYLE-ISOLATION-GUIDE.md](STYLE-ISOLATION-GUIDE.md) pour une documentation détaillée de l'isolation des styles.

## 🛠️ Getting Started

1. **New Users:** Start with [QUICK-REFERENCE.md](QUICK-REFERENCE.md)
2. **Detailed Setup:** Read [SHORTCODES.md](SHORTCODES.md)
3. **Advanced Configuration:** Check [MIGRATION-NOTES.md](MIGRATION-NOTES.md)
4. **Template Customization:** See [templates/README.md](templates/README.md)

## 🔍 Need Help?

- **Plugin Issues:** Check diagnostic tool at `/wp-admin/admin.php?page=maljani-diagnostic`
- **Documentation:** Read the appropriate guide above
- **Support:** Visit [https://kipdevwp.tech/](https://kipdevwp.tech/)
- **GitHub:** [https://github.com/kanji8210/maljani_travel_insurance_hub](https://github.com/kanji8210/maljani_travel_insurance_hub)

## 📱 Features Overview

### 🛒 Sales System
- Complete insurance policy sales forms
- Automatic premium calculation
- Client data validation
- Payment processing integration

### 👥 User Management
- Agent and customer dashboards
- Role-based access control
- Registration systems
- Profile management

### 📄 Document Generation
- Professional PDF policies
- Embassy letters with QR codes
- Security verification
- Custom branding

### ⚙️ Administration
- Policy and insurer management
- Sales tracking and reporting
- Diagnostic tools
- Configuration settings

---

*Last updated: July 28, 2025 - Version 1.0.0*
