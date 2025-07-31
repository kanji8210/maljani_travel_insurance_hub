# Guide de résolution des erreurs PDF sur Bluehost

## Problèmes courants et solutions

### 1. Erreur 500 - Causes possibles

1. **Limites de mémoire PHP** (le plus courant)
2. **Chemins de fichiers incorrects**
3. **Permissions de fichiers**
4. **Extensions PHP manquantes**
5. **Configuration TCPDF incompatible**

### 2. Étapes de diagnostic

1. **Accédez au script de diagnostic :**
   ```
   https://votresite.com/wp-content/plugins/maljani_travel_insurance_hub/includes/diagnostic-pdf-bluehost.php
   ```

2. **Vérifiez les points suivants :**
   - ✅ WordPress se charge correctement
   - ✅ TCPDF est trouvé et se charge
   - ✅ Base de données accessible
   - ✅ Extensions PHP requises (gd, mbstring, zlib, xml)
   - ✅ Permissions d'écriture

### 3. Solutions par problème

#### A. Problème de mémoire PHP
**Symptôme :** Erreur 500 ou "Fatal error: Allowed memory size"

**Solutions :**
1. Augmenter la limite dans `.htaccess` :
   ```
   php_value memory_limit 256M
   php_value max_execution_time 300
   ```

2. Ou dans `wp-config.php` :
   ```php
   ini_set('memory_limit', '256M');
   ini_set('max_execution_time', 300);
   ```

#### B. Problème de chemins
**Symptôme :** "WordPress core not found" ou "TCPDF not found"

**Solution :** Le script `generate-policy-pdf-bluehost.php` teste plusieurs chemins automatiquement.

#### C. Problème de permissions
**Symptôme :** Erreur d'écriture de fichier

**Solution :** Vérifier que le répertoire temp système est accessible en écriture.

#### D. Extensions PHP manquantes
**Symptôme :** Erreurs lors de la création du PDF

**Solution :** Contacter Bluehost pour activer les extensions manquantes.

### 4. Test des versions

Le plugin propose maintenant 3 options :

1. **PDF (Bluehost Version)** - Version optimisée (recommandée)
2. **Original PDF** - Version originale (pour administrateurs seulement)
3. **Diagnostic** - Script de diagnostic (pour administrateurs seulement)

### 5. Configuration Bluehost spécifique

Le fichier `tcpdf-config-bluehost.php` configure automatiquement :
- Chemins relatifs pour TCPDF
- Répertoire de cache système
- Paramètres de sécurité
- Gestion des erreurs

### 6. Vérification finale

1. Testez d'abord le diagnostic
2. Essayez la version Bluehost du PDF
3. Si problème persiste, vérifiez les logs d'erreur PHP
4. Contactez le support Bluehost si nécessaire

### 7. Logs d'erreur

Vérifiez les logs d'erreur dans :
- cPanel > Logs d'erreur
- Ou contactez Bluehost pour accès aux logs

### 8. Configuration recommandée pour Bluehost

```php
// Dans wp-config.php
define('WP_MEMORY_LIMIT', '256M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
ini_set('max_input_vars', 3000);
```

### 9. Support

Si les problèmes persistent après ces étapes :
1. Utilisez le script de diagnostic
2. Notez les erreurs exactes
3. Vérifiez la version PHP de Bluehost (recommandé: PHP 7.4+)
4. Contactez le support technique

---

**Note :** La version Bluehost inclut une gestion d'erreur améliorée et devrait résoudre la plupart des problèmes d'erreur 500.
