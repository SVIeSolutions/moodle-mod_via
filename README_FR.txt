Version 20.09.2013 


/* pour plus d'information sur les modifications lisez mod/via/version.php */


Procédure d'une NOUVELLE installation pour Moodle de 2.0 à 2.5 :
****************************************************************


1 - Copier les fichiers du module dans votre Moodle 2.x
	- Copier le dossier avec tout son contenu "mod / via" dans le répertoire "MoodleSite / mod" de votre Moodle
	- Copier le dossier 'UApi' à la racine du site - ceci permet accès aux activités sur tablette, mais nécessite de la version de via 5.5 et que l'application Mobile de Via soit téléchargé sur le mobile ou la tablette.

	* Optionnel 
		- Copier le dossier et son contenu "blocks / via" dans le répertoire "MoodleSite / blocks" de votre Moodle
			:  ce bloc affiche un lien rapide vers les enregistrements faits dans via présente dans les activités du cours

		- copier le dossier et son contenu "blocks / via_permanent" dans le répertoire "MoodleSite / blocks" de votre Moodle 
			:  ce bloc affiche un lien rapide vers les activités permanentes présentes dans le cours


2 - Cliquer sur le lien "Notifications" dans le menu "Administration"
	- Installer le module "Via - Enseignement virtuel"

	* Optionnel 
		- Installer le bloc "Via"
		- installer le bloc "Via Activités permanentes"


3 - Sous 'Administration du site-> Plugins-> Modules d'activité-> Via - Enseignement virtuel' entrez les informations de configuration pour l'API, 
    ceux-ci consistent de:
	- URL de l'API de Via
	- Clé Via (CieID)
	- Clé API (ApiID)
	- tester la connexion

	- Clé Moodle * ceci est nouveau, si vous n'avez pas encore reçu cette clé contacter SVIesolutions
	- tester la clé
     Dans la page de paramètres du plug-in vous pouvez configurer le plug in pour qu'il répondre à vos besoins
	- Vous voulez pouvoir ajouter les activités aux catégories que vous avez créées dans Via? Vous n'avez qu'à cochez la casse de de configurer les catégories
	- Vous voulez envoyer les invitations et rappels à partir de moodle (fortement suggéré) ou par Via?
	- Vous voulez que les participants confirment leur présence?
	- vous voulez pouvoir envoyer des courriels personnalisés
	- vous voulez que les informations des participants soient synchronisées avec leurs informations sur Via?


    

Procédure de MISE À JOUR pour Moodle de 2.0 à 2.5 :
****************************************************************

1 - Copier les fichiers du module dans votre Moodle 2.x
	- Copier le dossier avec tout son contenu "mod / via" dans le répertoire "MoodleSite / mod" de votre Moodle
	- Copier le dossier 'UApi' à la racine du site - ceci permet accès aux activités sur tablette, mais nécessite de la version de via 5.2

	* Optionnel 
		- Copier le dossier et son contenu "blocks / via" dans le répertoire "MoodleSite / blocks" de votre Moodle
		- copier le dossier et son contenu "blocks / via_permanent" dans le répertoire "MoodleSite / blocks" de votre Moodle 


2 - ENLEVER le code suivant dans lib/enrollib.php 
    à la fonction 'public function enrol_user()' 
    avant 'if ($userid == $USER->id)'
    dans moodle 2.0 ceci donne vers la ligne +/- 1098
    dans moodle 2.4 ceci donne vers la ligne +/- 1317
	
	/********************************************/
			
	/*Added in order to update Via participants */
			
	require_once($CFG->dirroot.'/mod/via/lib.php');
			
	add_participant_via($userid,  $instance->courseid);
			
	/********************************************/


3 - ENELEVER le code suivant dans lib/enrollib.php
    à la fonction 'public function unenrol_user()'
    avant '$DB->delete_records('user_lastaccess', array('userid'=>$userid, 'courseid'=>$courseid));'
    dans moodle 2.0 ceci donne vers la ligne +/- 1220
    dans moodle 2.4 ceci donne vers la ligne +/- 1448 

	/**********************************************/
			
	/*Added in order to update Via participants */
			
	require_once("$CFG->dirroot/mod/via/lib.php");
			
	remove_participant_via($userid, $courseid);
			
	/**********************************************/



2 - Ajouter la Clé moodle * ceci est nouveau de la version anteilleur, si vous n'avez pas encore reçu cette clé contacter SVIesolutions.

4 - Faire rouler le cron de façon manuel en appelant la page site/admin/cron.php pour mettre les nouveaux champs dans la BD via_participants utile pour la synchronisation à jour. 
    Ceci peut prendre quelque temps, nous vous suggérons donc de faire la mise à jour en moment plus tranquille. 

4 - Vider toutes les caches


Les activités existantes ne seront pas affectées par ce changement.




Procédure de mise à jour de l'URL pour Moodle de 2.0 à 2.5 :
****************************************************************

Dans une situation de nouvelle URL, Clé Via et Clé api, c'est mieux de désinstaller le plug-in complètement puis le réinstaller. 

Si vous ne le désinstallez pas, il faut supprimer toutes les activités dans mdl_via, tous les utilisateurs dans mdl_via_participants et mdl_via_users. 

Ces informations sont liées à l'ancienne URL et causeront des erreurs s’ils ne sont pas supprimés.
