<?php

class PostKintaiTask extends sfBaseTask
{
  public function configure()
  {
    mb_language('Japanese');
    mb_internal_encoding('utf-8');
    $this->namespace = 'opKintai';
    $this->name      = 'execute';
    $this->aliases = array('kintai-bot');
    $this->addOptions(array(
      new sfCommandOption('start-member-id', null, sfCommandOption::PARAMETER_OPTIONAL, 'Start member id', null),
      new sfCommandOption('end-member-id', null, sfCommandOption::PARAMETER_OPTIONAL, 'End member id', null),
    ));
    $this->breafDescription  = 'execute opGyoenKintaiPlugin bot';
  }

  public function execute($arguments = array(), $options = array())
  {
    echo "START KINTAI BOT.\n";
    $details = array();
    $databaseManager = new sfDatabaseManager($this->configuration);
    $service = $this->getZendGdata();
    $p = array();
    $dql = Doctrine_Query::create()->from("Member m")->where("m.is_active = ?","1");
    if (!is_null($options['start-member-id']) && is_numeric($options['start-member-id']))
    {
      $dql = $dql->andWhere('m.id >= ?', $options['start-member-id']);
    }
    if (!is_null($options['end-member-id']) && is_numeric($options['end-member-id']))
    {
      $dql = $dql->andWhere('m.id <= ?', $options['end-member-id']);
    }
    $members = $dql->execute();
    $rawKey = opConfig::get('op_kintai_spkey', null);
    $wid = $this->getRowId($service, $rawKey);
    foreach($members as $member)
    {
      //変数初期化
      list($memberId, $memberspkey, $memberWorkSheetId, $memberMasterSpkey, $memberMasterWorkSheetId) = array(null, null, null, null, null,);
      $memberId = $member->getId();
      $memberspkey = self::getMemberSpreadSheetKey($service, $memberId);

      if (!is_null($memberspkey))
      {
        $memberWorkSheetId = $this->getMemberWorkSheetId($service, $memberspkey);
      }
      $memberMasterSpkey = $this->getMemberMasterSpreadSheetKey($service, $memberId);
      if (!is_null($memberMasterSpkey))
      {
        $memberMasterWorkSheetId = $this->getMemberMasterWorkSheetId($service, $memberMasterSpkey);
      }
      echo "==== debug info =====\n";
      echo "Member Id : {$memberId}\n";
      echo "rawkey: {$rawKey} || rawid:{$wid}\n";
      echo "Key: {$memberspkey} || WorkSheetId: {$memberWorkSheetId}\n";
      echo "MasterSpkey: {$memberMasterSpkey} || MasterWorkSheetId: {$memberMasterWorkSheetId}\n";
      // スプレッドシートで勤怠報告しているメンバーの勤怠を処理する。
      if (!is_null($memberspkey) && !is_null($memberWorkSheetId))
      {
        $previousMonth = date('m', strtotime('-1 month'));
        $year = date('Y', strtotime('-1 month'));
        $today = date('d', strtotime('-1 month'));
        // 先月分の勤怠を処理する。
        for ($i = 1; checkdate($previousMonth, $i, $year); $i++)
        {
          $this->updateKintai($service, $memberId, $memberspkey, $memberWorkSheetId, $memberMasterSpkey, $memberMasterWorkSheetId, $year, $previousMonth, $i);
        }

        // 今月分の勤怠を処理する。
        for ($i = 1; $i < $today && checkdate(date('m'), $i, date('Y')); $i++)
        {
          $this->updateKintai($service, $memberId, $memberspkey, $memberWorkSheetId, $memberMasterSpkey, $memberMasterWorkSheetId, date('Y'), date('m'), $i);
        }
      }
      elseif (!is_null($memberMasterSpkey) && !is_null($memberMasterWorkSheetId))
      {
        $previousMonth = sprintf('%02d', date('m', strtotime('-1 month')));
        $year = date('Y', strtotime('-1 month'));
        $today = date('d', strtotime('-1 month'));
        // 先月分の勤怠を処理する。
        for ($i = 1; checkdate($previousMonth, $i, $year);$i++)
        {
          $j = sprintf('%02d', $i);
          echo "Scanning: ".$year."/".$previousMonth."/".$j."...";
          $u = new Zend_Gdata_Spreadsheets_ListQuery();
          $u->setSpreadsheetKey($rawKey);
          $u->setWorksheetId($wid);
          $query = 'id='.$memberId.' and date='.$year.'/'.$previousMonth.'/'.$j;
          $u->setSpreadsheetQuery($query);
          $lineList = $service->getListFeed($u);
          if (!$lineList->entries['0'])
          {
            echo "skip\n";
            continue;
          }
          $entry = $lineList->entries[0];
          $lines = $entry->getCustom();
          foreach ($lines as $line)
          {
            $key = $line->getColumnName();
            switch($key)
            {
              case 'date':
                $date = $line->getText();
                break;
              case 'data':
                $data = $line->getText();
                break;
              case 'comment':
                $comment = $line->getText();
                break;
              default:
                // 何もしない。
            }
          }

          if (12 == strlen($data))
          {
            $keitai = substr($data, 0, 1);
            if ('S' == $keitai)
            {
              $ssh = substr($data, 1, 2);
              $ssm = substr($data, 3, 2);
              $seh = substr($data, 5, 2);
              $sem = substr($data, 7, 2);
              $srest = substr($data, 9, 3);
              $srh = floor($srest / 60);
              $srm = $srest - ( $srh * 60 );
              if (0 == $srh)
              {
                $srh = '0';
              }
              if (0 == $srm)
              {
                $srm = '0';
              }
            }
            else
            {
              $zsh = substr($data, 1, 2);
              $zsm = substr($data, 3, 2);
              $zeh = substr($data, 5, 2);
              $zem = substr($data, 7, 2);
              $zrest = substr($data, 9, 3);
              $zrh = floor($zrest / 60);
              $zrm = $zrest - ( $zrh * 60 );
              if (0 == $zrh)
              {
                $zrh = '0';
              }
              if (0 == $zrm)
              {
                $zrm = '0';
              }
            }
          }
          elseif (24 == strlen($data))
          {
            $data1 = substr($data, 0, 12);
            $data2 = substr($data, 12, 12);
            $keitai1 = substr($data1, 0, 1);
            if('S' == $keitai1)
            {
              $ssh = substr($data1, 1, 2);
              $ssm = substr($data1, 3, 2);
              $seh = substr($data1, 5, 2);
              $sem = substr($data1, 7, 2);
              $srest = substr($data1, 9, 3);
              $srh = floor($srest / 60);
              $srm = $srest - ( $srh * 60 );
              if (0 == $srh)
              {
                $srh = '0';
              }
              if (0 == $srm)
              {
                $srm = '0';
              }
            }
            else
            {
              $zsh = substr($data1, 1, 2);
              $zsm = substr($data1, 3, 2);
              $zeh = substr($data1, 5, 2);
              $zem = substr($data1, 7, 2);
              $zrest = substr($data1, 9, 3);
              $zrh = floor($zrest / 60);
              $zrm = $zrest - ( $zrh * 60 );
              if (0 == $zrh)
              {
                $zrh = '0';
              }
              if (0 == $zrm)
              {
                $zrm = '0';
              }
            }
            if ('S' == $keitai2)
            {
              $ssh = substr($data2, 1, 2);
              $ssm = substr($data2, 3, 2);
              $seh = substr($data2, 5, 2);
              $sem = substr($data2, 7, 2);
              $srest = substr($data2, 9, 3);
              $srh = floor($srest / 60);
              $srm = $srest - ( $srh * 60 );
              if (0 == $srh)
              {
                $srh = '0';
              }
              if (0 == $srm)
              {
                $srm = '0';
              }
            }
            else
              {
              $zsh = substr($data2, 1, 2);
              $zsm = substr($data2, 3, 2);
              $zeh = substr($data2, 5, 2);
              $zem = substr($data2, 7, 2);
              $zrest = substr($data2, 9, 3);
              $zrh = floor($zrest / 60);
              $zrm = $zrest - ( $zrh * 60 );
              if (0 == $zrh)
              {
                $zrh = '0';
              }
              if (0 == $zrm)
              {
                $zrm = '0';
              }
            }
          }

          $detail = array("date"=>$date, "ssh"=>$ssh, "ssm"=>$ssm, "seh"=>$seh, "sem"=>$sem, "srh"=>$srh, "srm"=>$srm, "zsh"=>$zsh, "zsm"=>$zsm, "zeh"=>$zeh, "zem"=>$zem, "zrh"=>$zrh, "zrm"=>$zrm);
          list($date, $data, $data1, $data2, $keitai, $keitai1, $keitai2, $comment, $ssh, $ssm, $seh, $sem, $srh, $srm, $zsh, $zsm, $zeh, $zem, $zrh, $zrm) = array(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null);
          $this->updateMasterKintai($service, $memberId, $memberMasterSpkey, $memberMasterWorkSheetId, $year, $previousMonth, $i, $detail);
          $date = "";
          $detail = array();
        }

        // 今月の勤怠の処理をする。
        $today = date('d');
        for ($i = 1; $i < $today && checkdate(date('m'), $i, date('Y')); $i++)
        {
          $j = sprintf('%20d', $i);
          echo "Scanning: ".$year."/".date('m')."/".$j."... ";

          $w = new Zend_Gdata_Spreadsheets_ListQuery();
          $w->setSpreadsheetKey($rawKey);
          $w->setWorksheetId($wid);
          $query = 'id='.$memberId.' and date='.date('Y').'/'.date('m').'/'.$j;
          $w->setSpreadsheetQuery($query);
          $lineList = $service->getListFeed($w);
          if (!$lineList->entries['0'])
          {
            echo "skip\n";
            continue;
          }
          foreach ($lineList->entries as $entry)
          {
            $lines = $entry->getCustom();
            foreach ($lines as $line)
            {
              $key = $line->getColumnName();
              switch ($key)
              {
                case 'date':
                  $date = $line->getText();
                  break;
                case 'data':
                  $data = $line->getText();
                  break;
                case 'comment':
                  $comment = $line->getText();
                  break;
                default:
                  // 何もしない。
              }
            }
          }
          if (12 == strlen($data))
          {
            $keitai = substr($data, 0, 1);
            if ('S' == $keitai)
            {
              $ssh = substr($data, 1, 2);
              $ssm = substr($data, 3, 2);
              $seh = substr($data, 5, 2);
              $sem = substr($data, 7, 2);
              $srest = substr($data, 9, 3);
              $srh = floor($srest / 60);
              $srm = $srest - ( $srh * 60 );
              if (0 == $srh)
              {
                $srh = '0';
              }
              if (0 == $srm)
              {
                $srm = '0';
              }
            }
            else
            {
              $zsh = substr($data, 1, 2);
              $zsm = substr($data, 3, 2);
              $zeh = substr($data, 5, 2);
              $zem = substr($data, 7, 2);
              $zrest = substr($data, 9, 3);
              $zrh = floor($zrest / 60);
              $zrm = $zrest - ( $zrh * 60 );
              if (0 == $zrh)
              {
                $zrh = '0';
              }
              if (0 == $zrm)
              {
                $zrm = '0';
              }
            }
          }
          elseif (24 == strlen($data))
          {
            $data1 = substr($data, 0, 12);
            $data2 = substr($data, 12, 12);
            $keitai1 = substr($data1, 0, 1);
            if ('S' == $keitai1)
            {
              $ssh = substr($data1, 1, 2);
              $ssm = substr($data1, 3, 2);
              $seh = substr($data1, 5, 2);
              $sem = substr($data1, 7, 2);
              $srest = substr($data1, 9, 3);
              $srh = floor($srest / 60);
              $srm = $srest - ( $srh * 60 );
              if (0 == $srh)
              {
                $srh = '0';
              }
              if (0 == $srm)
              {
                $srm = '0';
              }
            }
            else
            {
              $zsh = substr($data1, 1, 2);
              $zsm = substr($data1, 3, 2);
              $zeh = substr($data1, 5, 2);
              $zem = substr($data1, 7, 2);
              $zrest = substr($data1, 9, 3);
              $zrh = floor($zrest / 60);
              $zrm = $zrest - ( $zrh * 60 );
              if (0 == $zrh)
              {
                $zrh = '0';
              }
              if (0 == $zrm)
              {
                $zrm = '0';
              }
            }
            if ('S' == $keitai2)
            {
              $ssh = substr($data2, 1, 2);
              $ssm = substr($data2, 3, 2);
              $seh = substr($data2, 5, 2);
              $sem = substr($data2, 7, 2);
              $srest = substr($data2, 9, 3);
              $srh = floor($srest / 60);
              $srm = $srest - ( $srh * 60 );
              if (0 == $srh)
              {
                $srh = '0';
              }
              if (0 == $srm)
              {
                $srm = '0';
              }
            }
            else
            {
              $zsh = substr($data2, 1, 2);
              $zsm = substr($data2, 3, 2);
              $zeh = substr($data2, 5, 2);
              $zem = substr($data2, 7, 2);
              $zrest = substr($data2, 9, 3);
              $zrh = floor($zrest / 60);
              $zrm = $zrest - ( $zrh * 60 );
              if (0 == $zrh)
              {
                $zrh = '0';
              }
              if (0 == $zrm)
              {
                $zrm = '0';
              }
            }
          }
          $detail = array("date"=>$date, "ssh"=>$ssh, "ssm"=>$ssm, "seh"=>$seh, "sem"=>$sem, "srh"=>$srh, "srm"=>$srm, "zsh"=>$zsh, "zsm"=>$zsm, "zeh"=>$zeh, "zem"=>$zem, "zrh"=>$zrh, "zrm"=>$zrm);
          // 変数を一括初期化。
          list($date, $data, $data1, $data2, $keitai, $keitai1, $keitai2, $comment, $ssh, $ssm, $seh, $sem, $srh, $srm, $zsh, $zsm, $zeh, $zem, $zrh, $zrm) = array(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null);
          $this->updateMasterKintai($service, $memberId, $memberMasterSpkey, $memberMasterWorkSheetId, date('Y'), date('m'), $i, $detail);

        }
      }
    }
  }

  public function getZendGdata()
  {
    $id = Doctrine::getTable('SnsConfig')->get('op_kintai_spid');
    $pw = Doctrine::getTable('SnsConfig')->get('op_kintai_sppw');
    $service = Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
    $client = Zend_Gdata_ClientLogin::getHttpClient($id, $pw, $service);

    return new Zend_Gdata_Spreadsheets($client);
  }

  private function getMemberSpreadSheetKey($service, $memberId)
  {
    $member = Doctrine::getTable('Member')->find($memberId);
    $memberEmailAddress = $member->getEmailAddress(false);
    $spreadsheetname = $memberEmailAddress."-kintai";
    $feed = $service->getSpreadsheetFeed();
    $i = 0;
    foreach($feed->entries as $entry)
    {
      if($entry->title->text === $spreadsheetname)
      {
        $aKey = split('/', $feed->entries[$i]->id->text);
        $SpreadsheetKey = $aKey[5];
        break;
      }
      $i++;
    }
    if ($SpreadsheetKey)
    {
      return $SpreadsheetKey;
    }
    else
    {
      return null;
    }
  }

  private function getMemberWorkSheetId($service, $spreadsheetKey)
  {
    $worksheetname = "勤怠明細";
    $DocumentQuery = new Zend_Gdata_Spreadsheets_DocumentQuery();
    $DocumentQuery->setSpreadsheetKey($spreadsheetKey);
    $SpreadsheetFeed = $service->getWorksheetFeed($DocumentQuery);
    $i = 0;
    foreach($SpreadsheetFeed->entries as $WorksheetEntry)
    {
      $worksheetId = split('/', $SpreadsheetFeed->entries[$i]->id->text);
      if($WorksheetEntry->title->text === $worksheetname)
      {
         $WorksheetId = $worksheetId[8];
         break;
      }
      $i++;
    }
    return $WorksheetId;
  }

  private function getMemberMasterSpreadSheetKey($service, $memberId)
  {
    $member = Doctrine::getTable('Member')->find($memberId);
    $memberEmailAddress = $member->getEmailAddress(false);
    $spreadsheetname = "(Master) ".$memberEmailAddress."-kintai";
    $feed = $service->getSpreadsheetFeed();
    $i = 0;
    foreach ($feed->entries as $entry)
    {
      if ( $entry->title->text === $spreadsheetname)
      {
        $aKey = split('/', $feed->entries[$i]->id->text);
        $SpreadsheetKey = $aKey[5];
        break;
      }
      $i++;
    }
    if ($SpreadsheetKey)
    {
      return $SpreadsheetKey;
    }
    else
    {
      return null;
    }
  }

  private function getMemberMasterWorkSheetId($service, $spreadsheetKey)
  {
    $worksheetname = "勤怠明細";
    $DocumentQuery = new Zend_Gdata_Spreadsheets_DocumentQuery();
    $DocumentQuery->setSpreadsheetKey($spreadsheetKey);
    $SpreadsheetFeed = $service->getWorksheetFeed($DocumentQuery);
    $i = 0;
    foreach($SpreadsheetFeed->entries as $WorksheetEntry)
    {
      $worksheetId = split('/', $SpreadsheetFeed->entries[$i]->id->text);
      if($WorksheetEntry->title->text === $worksheetname)
      {
         $WorksheetId = $worksheetId[8];
         break;
      }
      $i++;
    }

    return $WorksheetId;
  }

  private function getRowId($service, $spreadsheetKey)
  {
    $worksheetname = 'RAW';
    $DocumentQuery = new Zend_Gdata_Spreadsheets_DocumentQuery();
    $DocumentQuery->setSpreadsheetKey($spreadsheetKey);
    $SpreadsheetFeed = $service->getWorksheetFeed($DocumentQuery);
    $i = 0;
    foreach($SpreadsheetFeed->entries as $WorksheetEntry)
    {
      $worksheetId = split('/', $SpreadsheetFeed->entries[$i]->id->text);
      if($WorksheetEntry->title->text === $worksheetname)
      {
         $WorksheetId = $worksheetId[8];
         break;
      }
      $i++;
    }

    return $WorksheetId;
  }

  private function getPastDay($day)
  {
    $start = '2011/01/01';
    $diff = strtotime($day) - strtotime($start);
    $diffday = $diff / ( 3600 * 24 );

    return $diffday;
  }

  private function updateKintai($service, $memberId, $memberspkey, $memberWorkSheetId, $memberMasterSpkey, $memberMasterWorkSheetId, $year, $month, $i)
  {
    $q = new Zend_Gdata_Spreadsheets_ListQuery();
    $q->setSpreadsheetKey($memberspkey);
    $q->setWorksheetId($memberWorkSheetId);
    $query = 'date='.$year.'/'.$month.'/'.$i;
    $q->setSpreadsheetQuery($query);
    $lineList = $service->getListFeed($q);
    if (!$lineList)
    {
      continue;
    }
    $entry = $lineList->entries[0];
    $detail = array();
    foreach (array('date', 'ssh', 'ssm', 'seh', 'sem', 'srh', 'srm', 'zsh', 'zsm', 'zeh', 'zem', 'zrh', 'zrm') as $columnName)
    {
      $detail[$columnName] = $entry->getCustomByName($columnName)->getText();
    }

    $this->updateMasterKintai($service, $memberId, $memberMasterSpkey, $memberMasterWorkSheetId, $year, $month, $i, $detail);
  }

  private function updateMasterKintai($service, $memberId, $memberMasterSpkey, $memberMasterWorkSheetId, $year, $month, $i, $detail)
  {
    $r = new Zend_Gdata_Spreadsheets_ListQuery();
    $r->setSpreadsheetKey($memberMasterSpkey);
    $r->setWorksheetId($memberMasterWorkSheetId);
    $query = 'date='.$year.'/'.$month.'/'.$i;
    $r->setSpreadsheetQuery($query);
    $lineList = $service->getListFeed($r);
    if ($lineList)
    {
      $update = $service->updateRow($lineList->entries['0'], $detail);
      if ($update)
      {
        echo sprintf("UPDATE SUCCESS!(SpreadSheet) memberId: %s date: %s;\n", $memberId, $detail["date"]);
      }
      else
      {
        echo sprintf("ERROR! NO UPDATED.(SpreadSheet) Maybe Internal Server Error Occured on Google Service. memberId: %s date: %s;", $memberId, $detail["date"]);
      }
    }
    else
    {
      echo sprintf("ERROR! NO UPDATED.(SpreadSheet) Maybe Spreadsheet has been broken. memberId: %s date %s;", $memberId, $detail["date"]);
    }
  }
}
