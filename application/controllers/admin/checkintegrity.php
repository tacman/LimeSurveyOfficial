<?php
/*
 * LimeSurvey
 * Copyright (C) 2007 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 * $Id: checkintegrity.php 10760 2011-08-23 19:42:04Z aaronschmitz $
 */

/**
 * CheckIntegrity Controller
 *
 * This controller performs database repair functions.
 *
 * @package       LimeSurvey
 * @subpackage    Backend
 */
class CheckIntegrity extends CAction {

	public function run()
    {
		Yii::app()->loadHelper('database');
		if (isset($_GET['fixredundancy']))
			$this->fixredundancy();
		elseif (isset($_GET['fixintegrity']))
			$this->fixintegrity();
		else
			$this->index();
    }

    public function index()
    {
        if(Yii::app()->session['USER_RIGHT_CONFIGURATOR'] == 1)
        {
            $aData=$this->_checkintegrity();
            $this->getController()->_getAdminHeader();
            $this->getController()->_showadminmenu();
			$aData['clang']=Yii::app()->lang;
            $this->getController()->render('/admin/checkintegrity/check_view',$aData);
            $this->getController()->_getAdminFooter("http://docs.limesurvey.org", $this->getController()->lang->gT("LimeSurvey online manual"));
        }
    }

    public function fixredundancy()
    {
        $sDBPrefix=Yii::app()->db->tablePrefix;
        $clang = Yii::app()->lang;

        if(Yii::app()->session['USER_RIGHT_CONFIGURATOR'] == 1 && @$_POST['ok']=='Y')
        {
            $aDelete=$this->_checkintegrity();

            if (isset($aDelete['redundanttokentables']))
            {
                foreach($aDelete['redundanttokentables'] as $aTokenTable)
                {
                    Yii::app()->db->createCommand()->dropTable($aTokenTable['table']);
                    $aData['messages'][]= $clang->gT("Deleting token table:").' '.$aTokenTable['table'];
                }
            }

            if (isset($aDelete['redundantsurveytables']))
            {
                foreach($aDelete['redundantsurveytables'] as $aSurveyTable)
                {
                    Yii::app()->db->createCommand()->dropTable($aSurveyTable['table']);
                    $aData['messages'][]= $clang->gT("Deleting survey table:").' '.$aSurveyTable['table'];
                }
            }

            $this->getController()->_getAdminHeader();
            $this->getController()->_showadminmenu();
			$aData['clang']=$clang;
            $this->getController()->render('/admin/checkintegrity/fix_view',$aData);
            $this->getController()->_getAdminFooter("http://docs.limesurvey.org",  Yii::app()->lang->gT("LimeSurvey online manual"));
        }
    }

    public function fixintegrity()
    {
        $aData=array();
        $sDBPrefix=Yii::app()->db->tablePrefix;
        $clang =  Yii::app()->lang;
        if(Yii::app()->session['USER_RIGHT_CONFIGURATOR'] == 1 && @$_POST['ok']=='Y')
        {
            $aDelete=$this->_checkintegrity();

            // TMSW Conditions->Relevance:  Update this to process relevance instead
            if (isset($aDelete['conditions'])) {
                foreach ($aDelete['conditions'] as $aCondition) {
                    $sSQL = "DELETE FROM {{conditions}} WHERE cid={$aCondition['cid']}";
                    $oResult=db_query_or_false($sSQL) or safe_die ("Couldn't Delete ({$sSQL})");
                }

                $aData['messages'][]= sprintf($clang->gT("Deleting conditions: %u conditions deleted"),count($aDelete['conditions']));
            }

            if (isset($aDelete['questionattributes'])) {
                foreach ($aDelete['questionattributes'] as $aQuestionAttribute) {
                    $sSQL = "DELETE FROM {{question_attributes}} WHERE qid={$aQuestionAttribute['qid']}";
                    $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete ({$sSQL})");
                }
                $aData['messages'][]= sprintf($clang->gT("Deleting question attributes: %u attributes deleted"),count($aDelete['questionattributes']));
            }

            if ($aDelete['defaultvalues'])
            {
                $sSQL = "delete FROM {{defaultvalues}} where qid not in (select qid from {{questions}})";
                $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete default values ({$sSQL})");
                $aData['messages'][]= $clang->gT("Deleting orphaned default values.");
            }

            if ($aDelete['quotas'])
            {
                $sSQL = "delete FROM {{quota}} where sid not in (select sid from {{surveys}})";
                $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete quotas ($sSQL)");
                $aData['messages'][]= $clang->gT("Deleting orphaned quotas.");
            }

            if ($aDelete['quotals'])
            {
                $aData['messages'][]= $clang->gT("Deleting orphaned language settings.");
                $sSQL = "delete FROM {{quota_languagesettings}} where quotals_quota_id not in (select id from {{quota}})";
                $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete quotas ($sSQL)");
            }

            if ($aDelete['quotamembers'])
            {
                $sSQL = "delete FROM {{quota_members}} where quota_id not in (select id from {{quota}}) or qid not in (select qid from {{questions}}) or sid not in (select sid from {{surveys}})";
                $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete quota members ($sSQL)");
                $aData['messages'][]= $clang->gT("Deleting orphaned quota members.");
            }

            if (isset($aDelete['assessments'])) {
                foreach ($aDelete['assessments'] as $aAssessment) {
                    $sSQL = "DELETE FROM {{assessments}} WHERE id={$aAssessment['id']}";
                    $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete ($sSQL)");
                }
                $aData['messages'][]= sprintf($clang->gT("Deleting assessments: %u assessment entries deleted"), count($aDelete['assessments']));
            }

            if (isset($aDelete['answers'])) {
                foreach ($aDelete['answers'] as $aAnswer) {
                    $sSQL = "DELETE FROM {{answers}} WHERE qid={$aAnswer['qid']} AND code='{$aAnswer['code']}'";
                    $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete ($sSQL)");
                }
                $aData['messages'][]= sprintf($clang->gT("Deleting answers: %u answers deleted"), count($aDelete['answers']));
            }

            if (isset($aDelete['surveys'])) {
                foreach ($aDelete['surveys'] as $aSurvey) {
                    $sSQL = "DELETE FROM {{surveys}} WHERE sid={$aSurvey['sid']}";
                    $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete ({$sSQL})");
                }
                $aData['messages'][]= sprintf($clang->gT("Deleting surveys: %u surveys deleted"), count($aDelete['surveys']));
            }

            if (isset($aDelete['surveylanguagesettings'])) {
                foreach ($aDelete['surveylanguagesettings'] as $aSurveyLS) {
                    $sSQL = "DELETE FROM {{surveys_languagesettings}} WHERE surveyls_survey_id={$aSurveyLS['slid']}";
                    $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete surveylanguagesettings ({$sSQL})");//**************
                }
                $aData['messages'][]= sprintf($clang->gT("Deleting survey languagesettings: %u survey languagesettings deleted"), count($aDelete['surveylanguagesettings']));
            }

            if (isset($aDelete['questions'])) {
                foreach ($aDelete['questions'] as $aQuestion) {
                    $sSQL = "DELETE FROM {{questions}} WHERE qid={$aQuestion['qid']}";
                    $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete questions ({$sSQL})");
                }
                $aData['messages'][]= sprintf($clang->gT("Deleting questions: %u questions deleted"), count($aDelete['questions']));
            }


            if (isset($aDelete['groups'])) {
                foreach ($aDelete['groups'] as $aQuestion) {
                    $sSQL = "DELETE FROM {{groups}} WHERE gid={$aQuestion['gid']}";
                    $oResult = db_query_or_false($sSQL) or safe_die ("Couldn't delete groups ({$sSQL})");
                }
                $aData['messages'][]= sprintf($clang->gT("Deleting groups: %u groups deleted"), count($aDelete['groups']));
            }

            if (isset($aDelete['orphansurveytables']))
            {
                foreach($aDelete['orphansurveytables'] as $aSurveyTable)
                {
					Yii::app()->db->createCommand()->dropTable($aSurveyTable);
                    $aData['messages'][]= $clang->gT("Deleting orphan survey table:").' '.$aSurveyTable;
                }
            }

            if (isset($aDelete['orphantokentables']))
            {
                foreach($aDelete['orphantokentables'] as $aTokenTable)
                {
                    Yii::app()->db->createCommand()->dropTable($aTokenTable);
                    $aData['messages'][]= $clang->gT("Deleting orphan token table:").' '.$aTokenTable;
                }
            }

            $this->getController()->_getAdminHeader();
            $this->getController()->_showadminmenu();
			$aData['clang']=$clang;
            $this->getController()->render('/admin/checkintegrity/fix_view',$aData);
            $this->getController()->_getAdminFooter("http://docs.limesurvey.org", Yii::app()->lang->gT("LimeSurvey online manual"));
        }
    }


    /**
    * This function checks the LimeSurvey database for logical consistency and returns an according array
    * containing all issues in the particular tables.
    * @returns Array with all found issues.
    */
    protected function _checkintegrity()
    {
        $clang = Yii::app()->lang;

        /*** Plainly delete survey permissions if the survey or user does not exist ***/
        Yii::app()->db->createCommand("delete FROM {{survey_permissions}} where sid not in (select sid from {{surveys}})")->query();
        Yii::app()->db->createCommand("delete FROM {{survey_permissions}} where uid not in (select uid from {{users}})")->query();

        // Fix subquestions
        fixSubquestions();

        /*** Check for active survey tables with missing survey entry and rename them ***/
		$sDBPrefix = Yii::app()->db->tablePrefix;
        $sQuery = db_select_tables_like("{{survey}}\_%");
        $aResult =db_query_or_false($sQuery) or safe_die("Couldn't get list of conditions from database<br />$sQuery<br />");
        foreach ($aResult->readAll() as $aRow)
        {
           $sTableName=substr(reset($aRow),strlen($sDBPrefix));
           if ($sTableName=='survey_permissions' || $sTableName=='survey_links' || $sTableName=='survey_url_parameters') continue;
           $iSurveyID=substr($sTableName,strpos($sTableName,'_')+1);
           $sQuery="SELECT sid FROM {{surveys}} WHERE sid='{$iSurveyID}'";
           $oResult=db_query_or_false($sQuery) or safe_die ("Couldn't check questions table for qids<br />$qquery<br />");
           $iRowCount=$oResult->count();
           if ($iRowCount==0)
           {
                $sDate = date('YmdHis').rand(1,1000);
                $sOldTable = "survey_{$iSurveyID}";
                $sNewTable = "old_survey_{$iSurveyID}_{$sDate}";
                try {
					$deactivateresult = Yii::app()->db->createCommand()->renameTable($sDBPrefix.$sOldTable,$sDBPrefix.$sNewTable);
				} catch(CDbException $e) {
					die ("Couldn't make backup of the survey table. Please try again. The database reported the following error:<br />".htmlspecialchars($e)."<br />");
				}
                /* Not sure if that is still necessary if the rename procedure works right on CI
                if ($databasetype=='postgre')
                {
                    // If you deactivate a postgres table you have to rename the according sequence too and alter the id field to point to the changed sequence
                    $deactivatequery = db_rename_table($sOldTable.'_id_seq',$sNewTable.'_id_seq');
                    $deactivateresult = $this->db->query($deactivatequery) or die ("Couldn't make backup of the survey table. Please try again. The database reported the following error:<br />".htmlspecialchars($connect->ErrorMsg())."<br /><br />Survey was not deactivated either.<br /><br /><a href='$scriptname?sid={$postsid}'>".$clang->gT("Main Admin Screen")."</a>");
                    $setsequence="ALTER TABLE $sNewTable ALTER COLUMN id SET DEFAULT nextval('{$sNewTable}_id_seq'::regclass);";
                    $deactivateresult = $this->db->query($setsequence) or die ("Couldn't make backup of the survey table. Please try again. The database reported the following error:<br />".htmlspecialchars($connect->ErrorMsg())."<br /><br />Survey was not deactivated either.<br /><br /><a href='$scriptname?sid={$postsid}'>".$clang->gT("Main Admin Screen")."</a>");
                }
                */

           }
        }

        /*** Check for active token tables with missing survey entry ***/
        $sQuery = db_select_tables_like("{{tokens}}\_%");
        $aResult =db_query_or_false($sQuery) or safe_die("Couldn't get list of conditions from database<br />$sQuery<br />");
        foreach ($aResult->readAll() as $aRow)
        {
           $sTableName=substr(reset($aRow),strlen($sDBPrefix));
           $iSurveyID=substr($sTableName,strpos($sTableName,'_')+1);
           $sQuery="SELECT sid FROM {{surveys}} WHERE sid='{$iSurveyID}'";
           $oResult=db_query_or_false($sQuery) or safe_die ("Couldn't check questions table for qids<br />$qquery<br />");
           $iRowCount=$oResult->count();
           if ($iRowCount==0)
           {
                $sDate = date('YmdHis').rand(1,1000);
                $sOldTable = "tokens_{$iSurveyID}";
                $sNewTable = "old_tokens_{$iSurveyID}_{$sDate}";
				try {
					$deactivateresult = Yii::app()->db->createCommand()->renameTable($sDBPrefix.$sOldTable,$sDBPrefix.$sNewTable);
				} catch(CDbException $e) {
					die ("Couldn't make backup of the survey table. Please try again. The database reported the following error:<br />".htmlspecialchars($e)."<br />");
				}
                /* Not sure if that is still necessary if the rename procedure works right on CI
                if ($databasetype=='postgres')
                {
                    // If you deactivate a postgres table you have to rename the according sequence too and alter the id field to point to the changed sequence
                    $sOldTableJur = db_table_name_nq($sOldTable);
                    $deactivatequery = db_rename_table(db_table_name_nq($sOldTable),db_table_name_nq($sNewTable).'_tid_seq');
                    $deactivateresult = $this->db->query($deactivatequery) or die ("oldtable : ".$sOldTable. " / oldtableJur : ". $sOldTableJur . " / ".htmlspecialchars($deactivatequery)." / Could not rename the old sequence for this token table. The database reported the following error:<br />".htmlspecialchars($connect->ErrorMsg())."<br /><br /><a href='$scriptname?sid={$_GET['sid']}'>".$clang->gT("Main Admin Screen")."</a>");
                    $setsequence="ALTER TABLE ".db_table_name_nq($sNewTable)."_tid_seq ALTER COLUMN tid SET DEFAULT nextval('".db_table_name_nq($sNewTable)."_tid_seq'::regclass);";
                    $deactivateresult = $this->db->query($setsequence) or die (htmlspecialchars($setsequence)." Could not alter the field tid to point to the new sequence name for this token table. The database reported the following error:<br />".htmlspecialchars($connect->ErrorMsg())."<br /><br />Survey was not deactivated either.<br /><br /><a href='$scriptname?sid={$_GET['sid']}'>".$clang->gT("Main Admin Screen")."</a>");
                    $setidx="ALTER INDEX ".db_table_name_nq($sOldTable)."_idx RENAME TO ".db_table_name_nq($sNewTable)."_idx;";
                    $deactivateresult = $this->db->query($setidx) or die (htmlspecialchars($setidx)." Could not alter the index for this token table. The database reported the following error:<br />".htmlspecialchars($connect->ErrorMsg())."<br /><br />Survey was not deactivated either.<br /><br /><a href='$scriptname?sid={$_GET['sid']}'>".$clang->gT("Main Admin Screen")."</a>");
                } else {
                    $deactivateresult = $this->db->query($deactivatequery) or die ("Couldn't deactivate because:<br />\n".htmlspecialchars($connect->ErrorMsg())." - Query: ".htmlspecialchars($deactivatequery)." <br /><br />\n<a href='$scriptname?sid=$surveyid'>Admin</a>\n");
                }
                */

           }
        }

        /**********************************************************************/
        /*     Check conditions                                               */
        /**********************************************************************/
        // TMSW Conditions->Relevance:  Replace this with analysis of relevance
        $sQuery = "SELECT * FROM {{conditions}} ORDER BY cid";
        $oCResult =db_query_or_false($sQuery) or safe_die("Couldn't get list of conditions from database<br />$sQuery<br />");
        foreach ($oCResult->readAll() as $aRow)
        {
            $sQuery="SELECT qid FROM {{questions}} WHERE qid='{$aRow['qid']}'";
            $qresult=db_query_or_false($sQuery) or safe_die ("Couldn't check questions table for qids<br />$sQuery<br />");
            $iRowCount=$qresult->count();
            if (!$iRowCount) {
                $aDelete['conditions'][]=array('cid'=>$aRow['cid'], "reason"=>"No matching QID");
            }

            if ($aRow['cqid'] != 0)
            { // skip case with cqid=0 for codnitions on {TOKEN:EMAIL} for instance
                $sQuery = "SELECT qid FROM {{questions}} WHERE qid='{$aRow['cqid']}'";
                $oQResult=db_query_or_false($sQuery) or safe_die ("Couldn't check questions table for qids<br />$sQuery<br />");
                $iRowCount=$oQResult->count();
                if (!$iRowCount) {$aDelete['conditions'][]=array('cid'=>$aRow['cid'], "reason"=>$clang->gT("No matching CQID"));}
            }
            if ($aRow['cfieldname']) //Only do this if there actually is a "cfieldname"
            {
                if (preg_match("/^\+{0,1}[0-9]+X[0-9]+X*$/",$aRow['cfieldname']))
                { // only if cfieldname isn't Tag such as {TOKEN:EMAIL} or any other token
                    list ($surveyid, $gid, $rest) = explode("X", $aRow['cfieldname']);
                    $sQuery = "SELECT gid FROM {{groups}} WHERE gid=$gid";
                    $oGResult = db_query_or_false($sQuery) or safe_die ("Couldn't check conditional group matches<br />$sQuery<br />");
                    $iRowCount=$oGResult->count();
                    if (!$iRowCount) $aDelete['conditions'][]=array('cid'=>$aRow['cid'], "reason"=>$clang->gT("No matching CFIELDNAME group!")." ($gid) ({$aRow['cfieldname']})");
                }
            }
            elseif (!$aRow['cfieldname'])
            {
                $aDelete['conditions'][]=array('cid'=>$aRow['cid'], 'reason'=>$clang->gT("No CFIELDNAME field set!")." ({$aRow['cfieldname']})");
            }
        }

        /**********************************************************************/
        /*     Check question attributes                                      */
        /**********************************************************************/
        $sQuery = "SELECT * FROM {{question_attributes}} ORDER BY qid";
        $oResult =db_query_or_false($sQuery) or safe_die('Could not select question attributes');
        $iOrphanedQuestionAttributes=0;
        foreach ($oResult->readAll() as $aRow)
        {
            $sQuery = "SELECT * FROM {{questions}} WHERE qid = {$aRow['qid']}";
            $oResult =db_query_or_false($sQuery) or safe_die('Failed to select questions for question attributes');
            $qacount = $oResult->count();
            if (!$qacount) {
                $aDelete['questionattributes'][]=array('qid'=>$aRow['qid']);
            }
        } // foreach


        /**********************************************************************/
        /*     Check default values                                           */
        /**********************************************************************/
        $sQuery = "SELECT * FROM {{defaultvalues}} where qid not in (select qid from {{questions}})";
        $oResult =db_query_or_false($sQuery) or safe_die('Could not select default values');
        $aDelete['defaultvalues']=$oResult->count();

        /**********************************************************************/
        /*     Check quotas                                                   */
        /**********************************************************************/
        $sQuery = "SELECT * FROM {{quota}} where sid not in (select sid from {{surveys}})";
        $oResult =db_query_or_false($sQuery) or safe_die('Could not select quotas');
        $aDelete['quotas']=$oResult->count();

        /**********************************************************************/
        /*     Check quota languagesettings                                   */
        /**********************************************************************/
        $sQuery = "SELECT quotals_quota_id FROM {{quota_languagesettings}} where quotals_quota_id not in (select id from {{quota}})";
        try {
			$oResult =Yii::app()->db->createCommand($sQuery)->query();
		} catch(CDbException $e) {
			safe_die ($e);
		}
        $aDelete['quotals']=$oResult->count();

        /**********************************************************************/
        /*     Check quota members                                   */
        /**********************************************************************/
        $sQuery = "SELECT quota_id FROM {{quota_members}} where quota_id not in (select id from {{quota}}) or qid not in (select qid from {{questions}}) or sid not in (select sid from {{surveys}})";
		try {
			$oResult =Yii::app()->db->createCommand($sQuery)->query();
		} catch(CDbException $e) {
			safe_die ($e);
		}
        $aDelete['quotamembers']=$oResult->count();

        /**********************************************************************/
        /*     Check assessments                                              */
        /**********************************************************************/
        $sQuery = "SELECT id,name FROM {{assessments}} WHERE scope='T' ORDER BY sid";
        $oResult =db_query_or_false($sQuery) or safe_die ("Couldn't get list of assessments T");
        foreach ($oResult->readAll() as $aRow)
        {
            $sQuery = "SELECT sid FROM {{surveys}} WHERE sid = {$aRow['sid']}";
            $oResult2 =db_query_or_false($sQuery) or safe_die("Couldn't get assessments surveys");
            $iAssessmentCount = $oResult2->count();
            if (!$iAssessmentCount) {
                $aDelete['assessments'][]=array("id"=>$aRow['id'], "assessment"=>$aRow['name'], "reason"=>$clang->gT("No matching survey"));
            }
        } // while

        $sQuery = "SELECT id,name FROM {{assessments}} WHERE scope='G' ORDER BY gid";
        $oResult =db_query_or_false($sQuery) or safe_die ("Couldn't get list of assessments G");
        foreach ($oResult->readAll() as $aRow)
        {
            $sQuery = "SELECT * FROM {{groups}} WHERE gid = {$aRow['gid']}";
            $oResult2 =db_query_or_false($sQuery) or safe_die("Couldn't get assessments groups");
            $iAssessmentCount = $oResult2->count();
            if (!$iAssessmentCount) {
                $aDelete['assessments'][]=array("id"=>$aRow['id'], "assessment"=>$aRow['name'], "reason"=>$clang->gT("No matching group"));
            }
        }

        /**********************************************************************/
        /*     Check answers                                                  */
        /**********************************************************************/
        $sQuery = "SELECT qid,code FROM {{answers}} ORDER BY qid";
        $oResult =db_query_or_false($sQuery) or safe_die ("Couldn't get list of answers from database");
        foreach ($oResult->readAll() as $aRow)
        {
            $sQuery="SELECT qid FROM {{questions}} WHERE qid='{$aRow['qid']}'";
            $oResult2=db_query_or_false($sQuery) or safe_die ("Couldn't check questions table for qids from answers");
            $iAnswerCount=$oResult2->count();
            if (!$iAnswerCount) {
                $aDelete['answers'][]=array("qid"=>$aRow['qid'], "code"=>$aRow['code'], "reason"=>$clang->gT("No matching question"));
            }
        }

        /**********************************************************************/
        /*     Check surveys                                                  */
        /**********************************************************************/
        $sQuery = "SELECT * FROM {{surveys}} ORDER BY sid";//*******************************************
        $oResult =db_query_or_false($sQuery) or safe_die ("Couldn't get list of answers from database<br />$sQuery");
        foreach ($oResult->readAll() as $aRow)
        {
            $sQuery="SELECT surveyls_survey_id FROM {{surveys_languagesettings}} WHERE surveyls_survey_id='{$aRow['sid']}'";
            $oResult2=db_execute_assoc($sQuery) or safe_die ("Couldn't check survey language settings table for sids from surveys");
            $iSurveyLangSettingsCount=$oResult2->count();
            if (!$iSurveyLangSettingsCount) {
                $aDelete['surveys'][]=array("sid"=>$aRow['sid'], "reason"=>$clang->gT("Language specific settings missing"));
            }
        }

        /**********************************************************************/
        /*     Check survey language settings                                 */
        /**********************************************************************/
        $sQuery = "SELECT surveyls_survey_id FROM {{surveys_languagesettings}} where surveyls_survey_id not in (select sid from {{surveys}}) group by surveyls_survey_id order by surveyls_survey_id";
        $oResult =db_query_or_false($sQuery) or safe_die ("Couldn't get list of survey language settings  from database");
        foreach ($oResult->readAll() as $aRow)
        {
            $aDelete['surveylanguagesettings'][]=array('slid'=>$aRow['surveyls_survey_id'],'reason'=>$clang->gT("The related survey is missing."));
        }

        /**********************************************************************/
        /*     Check questions                                                */
        /**********************************************************************/
        $sQuery = "SELECT gid, qid, sid FROM {{questions}} ORDER BY sid, gid, qid";
        $oResult =db_query_or_false($sQuery) or safe_die ("Couldn't get list of questions from database");
        foreach ($oResult->readAll() as $aRow)
        {
            //Make sure the group exists
            $sQuery="SELECT gid FROM {{groups}} WHERE gid={$aRow['gid']}";
            $oResult2=db_execute_assoc($sQuery) or safe_die ("Couldn't check groups table for gids from questions<br />$qquery");
            if (!$oResult2->count())
            {
                $aDelete['questions'][]=array("qid"=>$aRow['qid'], "reason"=>$clang->gT("No matching group")." ({$aRow['gid']})");
            }
            //Make sure survey exists
            $sQuery="SELECT sid FROM {{surveys}} WHERE sid={$aRow['sid']}";
            $oResult2=db_execute_assoc($sQuery) or safe_die ("Couldn't check surveys table for sids from questions<br />$qquery");
            if (!$oResult2->count())
            {
                $aDelete['questions'][]=array("qid"=>$aRow['qid'], "reason"=>$clang->gT("There is no matching survey.")." ({$aRow['sid']})");
            }
        }

        /**********************************************************************/
        /*     Check groups                                                   */
        /**********************************************************************/
        $sQuery = "SELECT gid,sid FROM {{groups}} where sid not in (select sid from {{surveys}}) group by gid order by gid";
        $oResult=db_execute_assoc($sQuery) or safe_die ("Couldn't get list of groups for checking<br />$sQuery");
        foreach ($oResult->readAll() as $aRow)
        {
           $aDelete['groups'][]=array('gid'=>$aRow['gid'],'reason'=>$clang->gT("There is no matching survey.").' SID:'.$aRow['sid']);
        }

        /**********************************************************************/
        /*     Check old survey tables                                        */
        /**********************************************************************/
        //1: Get list of "old_survey" tables and extract the survey id
        //2: Check if that survey id still exists
        //3: If it doesn't offer it for deletion
        $sQuery = db_select_tables_like("{{old_survey}}%");
        $aResult =db_query_or_false($sQuery) or safe_die("Couldn't get list of conditions from database<br />$sQuery<br />");
        $aTables = $aResult->readAll();

        $aOldSIDs=array();
        $aSIDs=array();
        foreach($aTables as $sTable)
        {
            $sTable=reset($sTable);
            @list($sOldText, $SurveyText, $iSurveyID, $sDate)=explode("_", substr($sTable,strlen($sDBPrefix)));
            $aOldSIDs[]=$iSurveyID;
            $aFullOldSIDs[$iSurveyID][]=$sTable;
        }
        $aOldSIDs = array_unique($aOldSIDs);
        $sQuery = "SELECT sid FROM {{surveys}} ORDER BY sid";
        $oResult=db_execute_assoc($sQuery) or safe_die("Couldn't get unique survey ids");
        foreach ($oResult->readAll() as $aRow)
        {
            $aSIDs[]=$aRow['sid'];
        }
        foreach($aOldSIDs as $iOldSID)
        {
            if(!in_array($iOldSID, $aSIDs))
            {
                foreach($aFullOldSIDs[$iOldSID] as $sTableName)
                {
                    $aDelete['orphansurveytables'][]=$sTableName;
                }
            } else {
                foreach($aFullOldSIDs[$iOldSID] as $sTableName)
                {
                    $aTableParts = explode("_", substr($sTableName,strlen($sDBPrefix)));
                    if (count($aTableParts) == 4) {
                        $sOldText    = $aTableParts[0];
                        $SurveyText  = $aTableParts[1];
                        $iSurveyID   = $aTableParts[2];
                        $sDateTime   = $aTableParts[3];
                        $sType       = $clang->gT('responses');
                    } elseif (count($tableParts) == 5) {
                        //This is a timings table (
                        $sOldText    = $aTableParts[0];
                        $SurveyText  = $aTableParts[1];
                        $iSurveyID   = $aTableParts[2];
                        $sDateTime   = $aTableParts[4];
                        $sType       = $clang->gT('timings');
                    }
                    $iYear=substr($sDateTime, 0,4);
                    $iMonth=substr($sDateTime, 4,2);
                    $iDay=substr($sDateTime, 6, 2);
                    $iHour=substr($sDateTime, 8, 2);
                    $iMinute=substr($sDateTime, 10, 2);
                    $sDate=date("d M Y  H:i", mktime($iHour, $iMinute, 0, $iMonth, $iDay, $iYear));
                    $sQuery="SELECT * FROM ".$sTableName;
                    $oQRresult=db_execute_assoc($sQuery) or safe_die('Failed: '.$sQuery);
                    $iRecordcount=$oQRresult->count();
                    if($iRecordcount == 0) { // empty table - so add it to immediate deletion
                        $aDelete['orphansurveytables'][]=$sTableName;
                    } else {
                        $aOldSurveyTableAsk[]=array('table'=>$sTableName, 'details'=>sprintf($clang->gT("Survey ID %d saved at %s containing %d record(s) (%s)"), $iSurveyID, $sDate, $iRecordcount, $sType));
                    }
                }
            }
        }


        /**********************************************************************/
        /*     CHECK OLD TOKEN  TABLES                                        */
        /**********************************************************************/
        //1: Get list of "old_token" tables and extract the survey id
        //2: Check if that survey id still exists
        //3: If it doesn't offer it for deletion
        $sQuery = db_select_tables_like("{{old_token}}%");
        $aResult =db_query_or_false($sQuery) or safe_die("Couldn't get list of conditions from database<br />$sQuery<br />");
        $aTables = $aResult->readAll();

        $aOldTokenSIDs=array();
        $aTokenSIDs=array();
        $aFullOldTokenSIDs=array();

        foreach($aTables as $sTable)
        {
            $sTable=reset($sTable);

            @list($sOldText, $SurveyText, $iSurveyID, $sDateTime)=explode("_", substr($sTable,strlen($sDBPrefix)));
            $aTokenSIDs[]=$iSurveyID;
            $aFullOldTokenSIDs[$iSurveyID][]=$sTable;
        }
        $aOldTokenSIDs=array_unique($aOldTokenSIDs);
        $sQuery = "SELECT sid FROM {{surveys}} ORDER BY sid";
        $oResult=db_execute_assoc($sQuery) or safe_die("Couldn't get unique survey ids<br />$sQuery<br />");
        foreach ($oResult->readAll() as $aRow)
        {
            $aTokenSIDs[]=$aRow['sid'];
        }
        foreach($aOldTokenSIDs as $iOldTokenSID)
        {
            if(!in_array($iOldTokenSID, $aTokenSIDs))
            {
                foreach($aFullOldTokenSIDs[$iOldTokenSID] as $sTableName)
                {
                    $aDelete['orphantokentables'][]=$sTableName;
                }
            } else {
                foreach($aFullOldTokenSIDs[$iOldTokenSID] as $sTableName)
                {
                    list($sOldText, $sTokensText, $iSurveyID, $sDateTime)=explode("_", substr($sTableName,strlen($sDBPrefix)));
                    $iYear=substr($sDateTime, 0,4);
                    $iMonth=substr($sDateTime, 4,2);
                    $iDay=substr($sDateTime, 6, 2);
                    $iHour=substr($sDateTime, 8, 2);
                    $iMinute=substr($sDateTime, 10, 2);
                    $sDate=date("D, d M Y  h:i a", mktime($hour, $minute, 0, $month, $day, $year));
                    $sQuery="SELECT * FROM ".$tablename;

                    $oQRresult=db_execute_assoc($sQuery) or safe_die('Failed: '.$sQuery);
                    $iRecordcount=$oQRresult->count();
                    if($iRecordcount == 0) {
                        $aDelete['orphantokentables'][]=$sTableName;
                    }
                    else
                    {
                        $aOldTokenTableAsk[]=array('table'=>$sTableName, 'details'=>sprintf($clang->gT("Survey ID %d saved at %s containing %d record(s)"), $sid, $date, $jqcount));
                    }
                }
            }
        }

        if ($aDelete['defaultvalues']==0 && $aDelete['quotamembers']==0 &&
            $aDelete['quotas']==0  && $aDelete['quotals']==0  && count($aDelete)==4)
        {
            $aDelete['integrityok']=true;
        } else {
            $aDelete['integrityok']=false;
        }

        if (!isset($aOldTokenTableAsk) && !isset($aOldSurveyTableAsk))
        {
            $aDelete['redundancyok']=true;
        } else {
            $aDelete['redundancyok']=false;
            $aDelete['redundanttokentables']=array();
            $aDelete['redundantsurveytables']=array();
            if (isset($aOldTokenTableAsk))
            {
                 $aDelete['redundanttokentables']=$aOldTokenTableAsk;
            }
            if (isset($aOldSurveyTableAsk))
            {
                $aDelete['redundantsurveytables']=$aOldSurveyTableAsk;
            }
        }
        return $aDelete;
    }
}