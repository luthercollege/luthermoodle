<?php
define('CLI_SCRIPT', true);
require_once('../config.php');
global $DB;
$sql = "SELECT 
		T.username username,
		IF(ISNULL(T.Date),'',ELT(T.Date,'Sat-5/4','Fri-6/7','Tue-6/11','Wed-6/12','Fri-8/30')) Date,
		IF(ISNULL(T.ADM),'','Complete') ADM,
		IF(T.Math1 IS NOT NULL,T.Math1 - 1,'Incomplete') Math1,
		IF(T.Math2 IS NOT NULL,T.Math2 - 8,'Incomplete') Math2,
		IF(T.Math3 IS NOT NULL,T.Math3 - 16,'Incomplete') Math3,
		IF(ISNULL(T.MathQ),'','Complete') MathQ,
		IF(ISNULL(T.MLChoice),'',IF(T.MLChoice = 100,'Not Req','Req.')) MLChoice,
		IF(ISNULL(T.MLQ),'','Complete') MLQ,
		IF(ISNULL(T.MusChoice),'',IF(T.MusChoice = 1200,'Not Req','Req')) MusChoice,
		IF(ISNULL(T.MusChoice),'',IF(T.MusChoice = 1200,'Not Req',IF(ISNULL(T.MusExam),'Req. Not started',T.MusExam))) MusExam,
		IF(ISNULL(T.MusChoice),'',IF(T.MusChoice = 1200,'Not Req',IF(ISNULL(T.Piano),'Req. Not started',T.Piano))) Piano,
		IF(ISNULL(T.Change),'',FROM_UNIXTIME(T.Change)) AS DateChange,
		IF(ISNULL(T.MusHonors),'',T.MusHonors) AS MusHonors,
		IF(ISNULL(T.MathChoice),'',CONCAT('Math Exam ',ROUND(T.MathChoice/11) + 1)) MathChoice
		INTO OUTFILE 'rawgrades.csv'
		FIELDS TERMINATED BY ','  ENCLOSED BY '\"' 
		LINES TERMINATED BY '\r\n' 
		from 
		(SELECT u.username as username,
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND g.itemid = 917 AND g.finalgrade <> 0) AS 'Date',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND g.itemid = 912) AS 'ADM',
		(select ROUND(g.finalgrade) from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 909 or g.itemid = 5647)) AS 'Math1',
		(select ROUND(g.finalgrade,1) from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 910 or g.itemid = 5648)) AS 'Math2',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 911 or g.itemid = 5649)) AS 'Math3',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 913 or g.itemid = 5650)) AS 'MathQ',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 1279 or g.itemid = 63345)) AS 'MLChoice',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 914 or g.itemid = 5652)) AS 'MLQ',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND g.itemid = 1281) AS 'MusChoice',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 906 or g.itemid = 5654) AND g.rawgrade <> 0) AS 'MusExam',
		(select ROUND(g.finalgrade) from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 908 or g.itemid = 5656)) AS 'Piano',
		(select g.timemodified from mdl_grade_grades g where g.userid = u.id AND g.itemid = 917) AS 'Change',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND g.itemid = 907) AS 'MusHonors',
		(select g.finalgrade from mdl_grade_grades g where g.userid = u.id AND (g.itemid = 1280 or g.itemid = 5668)) AS 'MathChoice'
		FROM mdl_user u
		JOIN mdl_role_assignments a on a.userid=u.id
		JOIN mdl_context c on c.id=a.contextid
		WHERE c.contextlevel = 50
		AND (c.instanceid = 899 OR c.instanceid = 1296)
		AND a.roleid = 5) AS T
		WHERE
		NOT ISNULL(COALESCE(T.Date,T.ADM,T.MathChoice,T.Math1,T.Math2,T.Math3,T.MathQ,T.MLChoice,T.MLQ,T.MusChoice,T.MusExam,T.Piano,T.Change,T.MusHonors))
		ORDER BY username";
$query = $DB->execute($sql);

$sql = "SELECT u.username, att.responsesummary AS 'answer' 
	INTO OUTFILE 'musSection.csv'
	FIELDS TERMINATED BY ','  ENCLOSED BY '\"' 
	LINES TERMINATED BY '\r\n' 
	FROM mdl_quiz_attempts qatt
	JOIN mdl_user u ON u.id = qatt.userid
	JOIN mdl_question_attempts att on att.questionusageid = qatt.uniqueid
	WHERE att.questionid = 3085
	AND qatt.userid = u.id";
$query = $DB->execute($sql);
?>