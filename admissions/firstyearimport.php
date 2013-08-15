<?php // $Id$
// Edit course settings

		// CLEAN OUT ALL THE APOSTRAPHES IN THE FILE
		$importFile = file_get_contents('/moodledata/admissions/KATIE_DEPOSITED.csv');
		$replaceFile = str_replace("'","",$importFile);
        $importFile = fopen('/moodledata/admissions/katie_deposited.csv','w');
        fwrite($importFile,$replaceFile);
		fclose($importFile);
		// END OF APOSTRAPHE CLEANOUT
		unlink('/moodledata/admissions/firstyearimport.csv');
        $importFile = fopen('/moodledata/admissions/katie_deposited.csv','r');
        $targetFile = fopen('/moodledata/admissions/firstyearimport.csv','w');
        $line = fwrite($targetFile,"username,auth,password,firstname,lastname,email,course1,group1\n");
        while (($import = fgetcsv($importFile,1000,",")) !==  FALSE) {
			//Data line
			$username = split('@',$import[8]);
			if ($username[1] == 'luther.edu') {		// ONLY STUDENTS WITH VALID LUTHER EMAILS
				if ($import[27] == "FR") {
					$courseshort = 'REG-PLACE-2013';
				} else {
					$courseshort = 'ADM-TRANSFER-2013';
				}			
//				$fields = array($username[0],'ldap',$import[1],$import[2],$import[8],$courseshort,$import[20]);
				$line = fwrite($targetFile,"$username[0],ldap,xxxxxx,$import[1],$import[2],$import[8],$courseshort,$import[20]\n");
			}
//			echo $line."<br />";
		}
		fclose($importFile);
		fclose($targetFile);
?>