# new-greenmomit
J’ai effectué quelques améliorations et modifications sur le dernier plugin GreenMomit (2016-10-28 11:35:42 version) sous Jeedom 2.4

Quelles sont ces modifications (outre quelques fautes d’orthographe corrigées) :

Définition de nouveaux champs et remontées correspondantes des valeurs du GreenMomit : 
  a. calibration 
  b. histeresis 
  c. minTemperatureHeat 
  d. maxTemperatureHeat 
  e. design

Création et mise à jour de nouveaux champs INFO dans 
  a. « Etat » en texte (info) 
  b. « Relais Controlé par » en texte (info) 
  c. Etat Manuel (info) indique quand l’état est sur Manuel 
  d. Etat Off (info) indique quand l’état est sur Off 
  e. Etat Auto(info) indique quand l’état est sur Auto

Création et mise à jour de nouveau champs ACTION Etat Off (Action) éteint la chaudière et complète les actions existantes Auto et Manuel

Température de consigne (order et temperature) mise sur minTemperatureHeat quand : 
  a. L' ETAT est sur Off 
  b. L'ETAT est sur Auto - Calendar mais qu’aucune plage active du Calendar n’est active à l’instant.
    Dans le plugin officiel cette valeur raportée était forcée sur la consigne précédente, ce qui n’est pas correct

Paramètres "minValue" et "maxValue" de l'objet-slider 'thermostat' mis à minTemperatureHeat et maxTemperatureHeat et non pas définis statiquement à 12° et 28° lors de la synchronisation initiale

Integration complete dans le plugin Imperihome. 
  La definition du type Imperihome devThermostat est complète et le thermostat peut etre complètement géré depuis Imperihome dans ce mode.

Integration complete dans l'application Jeedom Mobile
  Rajout des generic types manquants exiges par Mobile

D’autres améliorations sont possibles, je laisse la main…

Les changements sont faciles à implémenter seul le fichier /var/www/html/plugins/greenmomit/core/class/greenmomit.class.php a été modifié :

Charger le fichier attaché greenmomit.class.php
Ecraser le fichier /var/www/html/plugins/greenmomit/core/class/greenmomit.class.php existant avec celui downloadé.
Optionnel : Supprimer le thermostat existant dans la config du plugin GreenMomit,
Cliquer sur « synchroniser mes équipements » dans la configuration générale du plugin Greenmomit.
Le thermostat réapparait avec les modifications, mais avec des IDs différentes si 3 optionnel effectué , donc certaines définitions peuvent être à refaire
J’ai évidemment coché la case ‘ne pas mettre à jour’ à côté du plugin GreenMomit dans le centre de Mise à jour pour éviter de perdre les modifs…

Si cette modification pouvait devenir la nouvelle base du plugin officiel, ce serait bien.# plugin-greenmomit
