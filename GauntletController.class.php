<?php

/**
 * Authors:
 *  - Equi
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'gauntlet',
 *		accessLevel = 'all',
 *		description = 'shows timer of Gauntlet',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauntlet sub .+',
 *		accessLevel = 'all',
 *		description = 'subscribe for a Gauntletraid',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauntlet subalt .+',
 *		accessLevel = 'all',
 *		description = 'shows a list to sub an alt',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauntlet rollqueue',
 *		accessLevel = 'all',
 *		description = 'rolls the subscriberlist for gauntlet',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauupdate',
 *		accessLevel = 'all',
 *		description = 'manual Gaunlet update',
 *		help        = 'gautimer.txt',
 *		alias		= 'gauset'
 *	)
 *	@DefineCommand(
 *		command     = 'gaukill',
 *		accessLevel = 'all',
 *		description = 'Gauntlet killtime',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gautrade',
 *		accessLevel = 'all',
 *		description = 'Gauntlet tradeskills',
 *		help        = 'gautimer.txt'
 *	)
 *
 * Gauntlet inventory part
 *
 *	@DefineCommand(
 *		command     = 'gaulist register',
 *		accessLevel = 'all',
 *		description = 'Registers a Gauntlet inventory for the Char',
 *		help        = 'gaulist.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gaulist',
 *		accessLevel = 'all',
 *		description = 'Manage the stuff u got and need',
 *		help        = 'gaulist.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gaulist add .+',
 *		accessLevel = 'all',
 *		description = 'Adds a item',
 *		help        = 'gaulist.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gaulist del .+',
 *		accessLevel = 'all',
 *		description = 'Removes a item',
 *		help        = 'gaulist.txt'
 *	)
 *
 * Gaubuff
 *
 *	@DefineCommand(
 *		command     = 'gaubuff',
 *		accessLevel = 'all',
 *		description = 'Handles timer for gauntlet buff',
 *		help        = 'gaubuff.txt'
 *	)
 */
class GauntletController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;

	/** @Inject */
	public $text;

	/** @Inject */
	public $settingManager;

	/** @Inject */
	public $chatBot;

	/** @Inject */
	public $db;

	/** @Inject */
	public $util;

	/** @Inject */
	public $altsController;

	/** @Logger */
	public $logger;

	/** @Inject */
	public $timerController;

	//(ref , image, need) 16 items without basic armor
	private $gaulisttab =   array(
		array(292507, 292793, 3), array(292509, 292775, 1), array(292508, 292776, 1), array(292510, 292774, 1),
		array(292514, 292764, 1), array(292515, 292780, 1), array(292516, 292792, 1), array(292532, 292760, 3),
		array(292533, 292788, 3), array(292529, 292779, 3), array(292530, 292759, 3), array(292524, 292784, 3),
		array(292538, 292772, 3), array(292525, 292763, 3), array(292526, 292777, 3), array(292528, 292778, 3),
		array(292517,292762,3)
	);

	//no need for sql db, because after downtime you need new time...
	private $gaunmem;

	/**
	 * @Setup
	 * This handler is called on bot startup.
	 */
	public function setup() {
		$this->db->loadSQLFile($this->moduleName, 'Gauntlet');
		$this->settingManager->add($this->moduleName, "gauntlet_timezone", "Choose you timezone", "edit", "text", "Europe/Berlin", "Europe/Berlin;America/New_York;Europe/Amsterdam;Europe/London", '', "mod");
		$this->settingManager->add($this->moduleName, 'gauntlet_times', 'Times to display timer alerts', 'edit', 'text', '2h 1h 30m 10m', '2h 1h 30m 10m', '', 'mod', 'gau_times.txt');
		$this->settingManager->add($this->moduleName, "gauntlet_portaltime", "Select how long Gauntletportal is open", 'edit', 'text', '6m30s', '6m30s', '', 'mod', 'gau_times.txt');
		$this->settingManager->add($this->moduleName, "gauntlet_color", "Color for the gauntlet chat timer", "edit", "color", "<font color=#999900>");
		$this->settingManager->add($this->moduleName, 'gauntlet_channels', 'Channels to display timer alerts', 'edit', 'text', 'both', 'guild;priv;both', '', 'mod', 'gau_times.txt');
		$this->settingManager->add($this->moduleName, 'gauntlet_autoset', 'Automaticly reset timer on restart or reconnect', "edit", "options", "0", "true;false", "1;0");
		$this->settingManager->add($this->moduleName, 'gaubuff_times', 'Times to display gaubuff timer alerts', 'edit', 'text', '30m 10m', '30m 10m', '', 'mod', 'gau_times.txt');
		$this->settingManager->add($this->moduleName, "gaubuff_logon", "Show gaubuff timer on logon", "edit", "options", "1", "Yes;No", "1;0");
	}

	private function tmTime($zz) {
		//This wouldnt be necessary if you would add timezone option for the bot into the configs :P
		$gtime = new DateTime();
		$gtime->setTimestamp($zz);
		$gtime->setTimezone(new DateTimeZone($this->settingManager->get('gauntlet_timezone')));
		return $gtime->format("D, H:i T (Y-m-d)");
	}

	public function gauntgetTime($zz) {
		//This wouldnt be necessary if you would add timezone option for the bot into the configs :P
		$timer = $this->timerController->get('Gauntlet');
		if ($timer === null) {
			return 0;
		}
		return $this->tmTime($timer->endtime + 61620*$zz);
	}

	private function checkalt($name, $name2) {
		$altInfo = $this->altsController->getAltInfo($name2);
		return in_array($name, $altInfo->getAllAlts());
	}

	/**
	 * @HandlesCommand("gautrade")
	 * @Matches("/^gautrade$/i")
	 */
	public function gautradeCommand($message, $channel, $sender, $sendto, $args) {
		$info = file_get_contents(__DIR__.'/gautrade');
		$msg = $this->text->makeLegacyBlob("Gauntlet Tradeskills", $info);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gauntlet sub .+")
	 * @Matches("/^gauntlet sub ([0-9]) ([a-z0-9]+)$/i")
	 */
	public function gauntletSubCommand($message, $channel, $sender, $sendto, $args) {
		//*** subscribe/unsubscribe for raid ***
		if (!isset($args[2])) {
			$args[2] = $sender;
		}
		if (($args[1] > 9) || ($args[1] < 0)) {
			$sendto->reply("This raid doesn't exist!");
			return;
		}
		if (!$this->checkalt($sender, $args[2])) {
			$sendto->reply("This is none of your chars!");
			return;
		}
		if (isset($this->gaumem[$args[1]][$args[2]])) {
			$this->gaumem[$args[1]][$args[2]]=0;
			unset($this->gaumem[$args[1]][$args[2]]);
			$msg = "You removed <highlight>$args[2]<end> from the Gauntlet at ".$this->gauntgetTime($args[1]);
		} else {
			$this->gaumem[$args[1]][$args[2]]=1;
			$msg = "<highlight>$args[2]<end> is now subscribed for the Gauntlet at ".$this->gauntgetTime($args[1]);
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gauntlet subalt .+")
	 * @Matches("/^gauntlet subalt ([0-9]) ([a-z0-9]+)$/i")
	 */
	public function gauntletSubaltCommand($message, $channel, $sender, $sendto, $args) {
		//*** subscribe/unsubscribe for raid if u have too many alts! ***
		if (!isset($args[2])) {
			$args[2] = $sender;
		};
		if (($args[1] > 9) || ($args[1] < 0)) {
			$sendto->reply("This raid doesn't exist!");
			return;
		}
		if (!$this->checkalt($sender, $args[2])) {
			$sendto->reply("This is none of your chars!");
			return;
		}
		$msg = "Subscription for the Gauntlet at ".$this->gauntgetTime($args[1])." with the main $args[2]:\n\n";
		$altInfo = $this->altsController->getAltInfo($args[2]);
                $allAlts = $altInfo->getAllAlts();
                sort($allAlts);
		foreach ($allAlts as $alt) {
			$msg .= "     - <a href='chatcmd:///tell <myname> <symbol>gauntlet sub $args[1] $alt'>$alt</a>\n";
		}
		$msg = $this->text->makeBlob("Un/Subscribe", $msg);
		$sendto->reply($msg, $sendto);
	}

	private function gauRollQueue() {
		$gau = $this->gaumem;
		unset($this->gaumem);
		for ($z = 1; $z <= 9; $z++) {
			foreach($gau[$z] as $key => $value) {
				$this->gaumem[$z-1][$key]=1;
			}
		}
		unset($gau);
	}

	/**
	 * @HandlesCommand("gauntlet rollqueue")
	 * @Matches("/^gauntlet rollqueue$/i")
	 */
	public function gauntletRollqueueCommand($message, $channel, $sender, $sendto, $args) {
		//*** roll manual subscribe queue raid; only raidleader! ***
		$this->gauRollQueue();
		$msg = "Manual queue rolled!";
		$sendto->reply($msg);
	}

	public function getGauCreator() {
		$timer = $this->timerController->get('Gauntlet');
		if ($timer === null) {
			return null;
		}
		return json_decode($timer->data, true);
	}

	/**
	 * This command handler shows gauntlet.
	 *
	 * @HandlesCommand("gauntlet")
	 * @Matches("/^gauntlet$/i")
	 */
	public function gauntletCommand($message, $channel, $sender, $sendto, $args) {
		$timer = $this->timerController->get('Gauntlet');
		if ($timer === null) {
			$sendto->reply("No Gauntlettimer set! Seems like someone deleted it.");
			return;
		}
		$gautimer = $timer->endtime;
		$dt = $gautimer-time();
		$list = "Tradeskill: [<a href='chatcmd:///tell <myname> <symbol>gautrade'>Click me</a>]\n";
		$creatorinfo = $this->getGauCreator();
		$list .= "Timer updated by <highlight>".$creatorinfo['creator']."<end> at <highlight>".$this->tmTime($creatorinfo['createtime'])."<end>\n\n";

		//alts handler more or less 8! Every blob has its max size, so we need such a thing
		$altInfo = $this->altsController->getAltInfo($sender);
		$aashort = count($altInfo->getAllAlts()) >= 9;

		//spawntimes
		for ($z = 0; $z <= 9; $z++) {
			$list .= "    - ".$this->gauntgetTime($z)."\n";
			//subscriber list
			if (count($this->gaumem[$z])>0) {
				$list .= "         <yellow>";
                                $list .= join(', ', array_keys($this->gaumem[$z]));
				$list .= " <end>\n         Sub/Unsub with |";
			} else {
				$list .= "         Sub/Unsub with |";
			}
			//add altslist
			if ($aashort == false) {
				foreach ($altInfo->getAllAlts() as $alt) {
					$list .= "<a href='chatcmd:///tell <myname> <symbol>gauntlet sub $z $alt'>$alt</a>|";
				}
			} else {
				$list .= "<a href='chatcmd:///tell <myname> <symbol>gauntlet sub $z $altInfo->main'>$altInfo->main</a>|";
				$list .= "<a href='chatcmd:///tell <myname> <symbol>gauntlet subalt $z $altInfo->main'>Other chars</a>|";
			}
			$list .= "\n\n";
		}
		$link = $this->text->makeBlob("Spawntimes for Portals", $list);

		//if portal is open
		$gptime = time()+61620-$gautimer;
		if (($gptime > 0) && ($gptime <=($this->settingManager->get('gauntlet_portaltime')*60))) {
			$gptime = $this->settingManager->get('gauntlet_portaltime')*60-$gptime;
			$msg = "<highlight>Portal is open for <end><red>".$this->util->unixtimeToReadable($gptime)."<end><highlight>!<end> ".$link;
			//otherwise show normal style
		} else {
			$msg = "<highlight>".$this->util->unixtimeToReadable($dt)."<end> until Vizaresh is vulnerable. ".$link;
		}

		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gaukill")
	 * @Matches("/^gaukill$/i")
	 */
	public function gaukillCommand($message, $channel, $sender, $sendto, $args) {
		//Gauntlet portal will be there again in 61620 secs!
		$this->setGauTime(time()+61620, $sender, time());
		//roll subscribe list
		$this->gauRollQueue();
		//send something
		$msg="Bot was updated manually! Vizaresh will be vulnerable at ".$this->gauntgetTime(0)."\n (respawn is every 17 hours and 7 minutes)";
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gauupdate")
	 * @Matches("/^gauupdate ([a-z0-9 ]+)$/i")
	 */
	public function gauupdateCommand($message, $channel, $sender, $sendto, $args) {
		$newSpawn = $this->util->parseTime($args[1]);
		if ($newSpawn < 1) {
			$msg = "You must enter a valid time parameter for the gauntlet update time.";
			$sendto->reply($msg);
			return;
		}
                $newSpawn += time();
		$this->setGauTime($newSpawn, $sender, time());
		$msg="Bot was updated manually! Vizaresh will be vulnerable at ".$this->gauntgetTime(0)."\n (Normal respawn is every 17h07m)";
		$sendto->reply($msg);
	}

	//**************************************
	//***   Gauntlet inventory from here on
	//**************************************

	private function checkZero($number) {
		return max(0, $number);
	}

	private function gaudbexists($name) {
		$row = $this->db->queryRow("SELECT * FROM gauntlet WHERE `player` = ? LIMIT 1", $name);

		return $row !== null;
	}

	private function bastioninventory($name, $ac) {
		//check is done earlier, get data hier
		$row = $this->db->queryRow("SELECT * FROM gauntlet WHERE `player` = ? LIMIT 1", $name);
		$tem = unserialize($row->items);
		if (($ac<0)&&($ac>3)) {
			$ac = 1;
		}
		//Do blob box
                $list = "Tradeskill: [<a href='chatcmd:///tell <myname> <symbol>gautrade'>Click me</a>]\n".
                        "Needed items for: [<a href='chatcmd:///tell <myname> <symbol>gaulist $name 1'>1 Armor</a>|".
                                           "<a href='chatcmd:///tell <myname> <symbol>gaulist $name 2'>2 Armor</a>|".
                                           "<a href='chatcmd:///tell <myname> <symbol>gaulist $name 3'>3 Armor</a>]\n";
		$list .= "Items needed for ".$ac." Bastionarmorparts.\n<green>[Amount you have]<end>|<red>[Amount you need]<end>\n[+]=increase Item      [-]=decrease Item\n\n";

		for ($i = 0; $i <= 16; $i++) {
			$list .= "    <a href='itemref://".$this->gaulisttab[$i][0]."/".$this->gaulisttab[$i][0]."/".$this->gaulisttab[$i][0]."'><img src='rdb://".$this->gaulisttab[$i][1]."'></a>    ";
			if ((($i+1) % 4)==0) {
				$list .= "\n";
				for ($ii = $i-3; $ii<=$i; $ii++) {
					$list .= "[<a href='chatcmd:///tell <myname> <symbol>gaulist add $name $ii'> + </a>|<green>".$tem[$ii]."<end>|<red>".$this->checkZero(($ac*$this->gaulisttab[$ii][2])-$tem[$ii])."<end>|<a href='chatcmd:///tell <myname> <symbol>gaulist del $name $ii'> - </a>] ";
				}
				$list .= "\n";
			} elseif ($i==16) {
				$list .= "\n[<a href='chatcmd:///tell <myname> <symbol>gaulist add $name $i'> + </a>|<green>".$tem[$i]."<end>|<red>".$this->checkZero(($ac*$this->gaulisttab[$i][2])-$tem[$i])."<end>|<a href='chatcmd:///tell <myname> <symbol>gaulist del $name $i'> - </a>]\n";
			}
		}
		$list .= "\n                         <a href='chatcmd:///tell <myname> <symbol>gaulist $name $ac'>Refresh</a>";
		$link = $this->text->makeBlob("Bastion inventory for $name", $list);
		$tem = "Bastion inventory: ".$link;
		return $tem;
	}

	/**
	 * @HandlesCommand("gaulist register")
	 * @Matches("/^gaulist register$/i")
	 */
	public function gaulistRegisterCommand($message, $channel, $sender, $sendto, $args) {
		//Creates a db for your char
		//1. Check if player is in db and create if not
		if ($this->gaudbexists($sender)==false) {
			$this->db->exec("INSERT INTO `gauntlet` (`player`, `items`) VALUES (? , ?)", $sender, serialize(array(0,0,0,0,0, 0,0,0,0,0, 0,0,0,0,0, 0,0)));
			$msg = "Gauntlet inventory created for $sender.";
		} else {
			$msg = "You already have a Gauntlet inventory!";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gaulist")
	 * @Matches("/^gaulist$/i")
	 * @Matches("/^gaulist ([a-z0-9]+)$/i")
	 * @Matches("/^gaulist ([a-z0-9]+) ([0-9])$/i")
	 */
	public function gaulistCommand($message, $channel, $sender, $sendto, $args) {
		if (count($args)==3) {
			$name = ucfirst(strtolower($args[1]));
			$ac = $args[2];
		} elseif (count($args)==2) {
			if (preg_match('/^\d+$/', $args[1])) {
				$name = $sender;
				$ac = $args[1];
			} else {
				$name = ucfirst(strtolower($args[1]));
				$ac = 1;
			}
		} else {
			$name = $sender;
			$ac = 1;
		}
		//check and get Bastioninventory
		if ($this->gaudbexists($name)) {
			$msg = $this->bastioninventory($name, $ac);
		} else {
			$msg = "No Bastion inventory found for $name, use <symbol>gaulist register.";
		}
		$sendto->reply($msg);
	}

	protected function altCheck($sendto, $sender, $name) {
		if ($this->gaudbexists($name) && ($this->checkalt($sender, $name))) {
			return true;
		}
		$sendto->reply("Player \"$name\" doesn't exist or is not your alt.");
		return false;
	}

	/**
	 * @HandlesCommand("gaulist add .+")
	 * @Matches("/^gaulist add ([a-z0-9]+) ([0-9]+)$/i")
	 */
	public function gaulistAddCommand($message, $channel, $sender, $sendto, $args) {
		$tt = array();
		$tt = array_fill(0,16,0);
		$name = ucfirst(strtolower($args[1]));
		//***Check and increase item
		if ($this->altCheck($sendto, $sender, $name) === false) {
			return;
		}
		if (($args[2]>=0)&&($args[2]<17)) {
			$row = $this->db->queryRow("SELECT * FROM gauntlet WHERE `player` = ? LIMIT 1", $name);
			$tt = unserialize($row->items);
			++$tt[$args[2]];
			$this->db->exec("UPDATE `gauntlet` SET `items` = ? WHERE `player` = ?", serialize($tt), $name);
			$msg = "Item increased!";
		} else {
			$msg = "No valid itemID.";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gaulist del .+")
	 * @Matches("/^gaulist del ([a-z0-9]+) ([0-9]+)$/i")
	 */
	public function gaulistDelCommand($message, $channel, $sender, $sendto, $args) {
		$tt = array();
		$tt = array_fill(0,16,0);
		$name = ucfirst(strtolower($args[1]));
		//***Check and increase item
		if ($this->altCheck($sendto, $sender, $name) === false) {
			return;
		}
		if (($args[2]>=0)&&($args[2]<17)) {
			$row = $this->db->queryRow("SELECT * FROM gauntlet WHERE `player` = ? LIMIT 1", $name);
			$tt = unserialize($row->items);
			if ($tt[$args[2]]>0) {
				--$tt[$args[2]];
				$this->db->exec("UPDATE `gauntlet` SET `items` = ? WHERE `player` = ?", serialize($tt), $name);
				$msg = "Item decreased!";
			} else {
				$msg = "Item is already at zero."; }
		} else {
			$msg = "No valid itemID.";
		}
		$sendto->reply($msg);
	}

	//**************************************
	//***   Gaubuff timer
	//**************************************

	public function getGauBuffCreator() {
		$timer = $this->timerController->get('Gaubuff');
		if ($timer === null) {
			return null;
		}
		return json_decode($timer->data, true);
	}

	public function setGaubuff($time, $creator, $createtime) {
		$alerts = array();
		foreach (explode(' ', $this->settingManager->get('gaubuff_times')) as $utime) {
			$alertTimes[] = $this->util->parseTime($utime);
		}
		$alertTimes[] = 0;                  //timer runs out
		foreach ($alertTimes as $alertTime) {
			if (($time - $alertTime)>time()) {
				$alert = new stdClass;
				$alert->time = $time - $alertTime;
				if ($alertTime == 0) {
					$alert->message = $this->settingManager->get('gauntlet_color')."Gauntlet buff <highlight>expired<end>!<end>";
				} else {
					$alert->message = $this->settingManager->get('gauntlet_color')."Gauntlet buff runs out in <highlight>".$this->util->unixtimeToReadable($alertTime)."<end>!<end>";
				}
				$alerts []= $alert;
			}
		}
		$data = array();
		$data['createtime'] = $createtime;
		$data['creator'] = $creator;
		$data['repeat'] = 0;
		//*** Add Timers
		$this->timerController->remove('Gaubuff');
		$this->timerController->add('Gaubuff', $this->chatBot->vars['name'], $this->settingManager->get('gauntlet_channels'), $alerts, "GauntletController.gaubuffcallback", json_encode($data));
	}

	public function gaubuffcallback($timer, $alert) {
		if ($this->settingManager->get('gauntlet_channels')== "priv") {
			$this->chatBot->sendPrivate($alert->message, true);
		} else if ($this->settingManager->get('gauntlet_channels')== "guild") {
			$this->chatBot->sendGuild($alert->message, true);
		} else if ($this->settingManager->get('gauntlet_channels')== "both") {
			$this->chatBot->sendPrivate($alert->message, true);
			$this->chatBot->sendGuild($alert->message, true);
		}
	}

	/**
	 * @Event("logOn")
	 * @Description("Sends gaubuff message on logon")
	 */
	public function gaubufflogonEvent($eventObj) {
		$sender = $eventObj->sender;
		if ($this->chatBot->isReady() && (isset($this->chatBot->guildmembers[$sender])) && ($this->settingManager->get('gaubuff_logon'))) {
			$timer = $this->timerController->get('Gaubuff');
			if ($timer !== null) {
				$this->chatBot->sendTell($this->settingManager->get('gauntlet_color')."Gauntlet buff runs out in <highlight>".$this->util->unixtimeToReadable($timer->endtime - time())."<end><end>!", $sender);
			}
		}
	}

	/**
	 * @Event("joinPriv")
	 * @Description("Sends gaubuff message on join")
	 */
	public function privateChannelJoinEvent($eventObj) {
		$sender = $eventObj->sender;
		if ($this->settingManager->get('gaubuff_logon')) {
			$timer = $this->timerController->get('Gaubuff');
			if ($timer !== null) {
				$this->chatBot->sendTell($this->settingManager->get('gauntlet_color')."Gauntlet buff runs out in <highlight>".$this->util->unixtimeToReadable($timer->endtime - time())."<end><end>!", $sender);
			}
		}
	}

	/**
	 * This command handler shows gauntlet.
	 *
	 * @HandlesCommand("gaubuff")
	 * @Matches("/^gaubuff$/i")
	 * @Matches("/^gaubuff ([a-z0-9 ]+)$/i")
	 */
	public function gaubuffCommand($message, $channel, $sender, $sendto, $args) {
		//set time
		if (isset($args[1])) {
			$buffEnds = $this->util->parseTime($args[1]);
			if ($buffEnds < 1) {
				$msg = "You must enter a valid time parameter for the gauntlet buff time.";
				$sendto->reply($msg);
				return;
			}
			$buffEnds += time();
			$this->setGaubuff($buffEnds, $sender, time());
			$msg = "Gauntletbuff timer has been set and expires at <highlight>".$this->tmTime($buffEnds)."<end>.";
			$sendto->reply($msg);
			return;
		}
		//get time
		$timer = $this->timerController->get('Gaubuff');
		if ($timer === null) {
			$sendto->reply("No Gauntlet buff available!");
			return;
		}
		$gaubuff = $timer->endtime - time();
		$msg = "Gauntlet buff runs out in <highlight>".$this->util->unixtimeToReadable($gaubuff)."<end>.";
		$sendto->reply($msg);
	}

	//**************************************
	//***   Gauntlet event things
	//**************************************

	/**
	 * @Event("connect")
	 * @Description("Initialize timers")
	 */
	public function intializeTimersEvent($eventObj) {
		if ($this->settingManager->get('gauntlet_autoset')) {
			$this->setGauTime(time()+480, $this->chatBot->vars['name'], time());
		}
	}

	private function gauAlert($tstr) {
		foreach($this->gaumem[0] as $key => $value) {
			$altInfo = $this->altsController->getAltInfo($key);
			foreach ($altInfo->getOnlineAlts() as $name) {
				if ($name != $key) {
					$this->chatBot->sendTell("<red>Gauntlet is in $tstr (subscribed with $key).<end>", $name);
				} else {
					$this->chatBot->sendTell("<red>Gauntlet is in $tstr.<end>", $name);
				}
			}
		}
	}

	public function gauntletcallback($timer, $alert) {
		if ($timer->endtime - $alert->time == 1800) {
			$this->gauAlert("30 min");
			//this could be upgraded by adding setting etc
		}
		if ($this->settingManager->get('gauntlet_channels')== "priv") {
			$this->chatBot->sendPrivate($alert->message, true);
		} else if ($this->settingManager->get('gauntlet_channels')== "guild") {
			$this->chatBot->sendGuild($alert->message, true);
		} else if ($this->settingManager->get('gauntlet_channels')== "both") {
			$this->chatBot->sendPrivate($alert->message, true);
			$this->chatBot->sendGuild($alert->message, true);
		}
		if (count($timer->alerts) == 0) {
			$data= json_decode($timer->data, true);
			$this->setGauTime($timer->endtime + $data['repeat'], $data['creator'], $data['createtime']);
			//roll subscribe list, keeeeeppp on rollin rollin rollin...^^
			$this->gauRollQueue();
		}
	}

	public function setGauTime($time, $creator, $createtime) {
		$alerts = array();
		$portaltime = $this->util->parseTime($this->settingManager->get('gauntlet_portaltime'));
		foreach (explode(' ', $this->settingManager->get('gauntlet_times')) as $utime) {
			$alertTimes[] = $this->util->parseTime($utime);
		}
		$alertTimes[] = 61620-$portaltime;  //portal closes
		$alertTimes[] = 0;                  //vulnerable
		$alertTimes[] = 420;                //spawn
		//make sure the order is correct...maybe this little thing could be included in the Timecontroller.class.php when adding
		rsort($alertTimes);
		foreach ($alertTimes as $alertTime) {
			if (($time - $alertTime)>time()) {
				$alert = new stdClass;
				$alert->time = $time - $alertTime;
				if ($alertTime == 0) {
					$alert->message = $this->settingManager->get('gauntlet_color')."Vizaresh <highlight>VULNERABLE/DOWN<end>!<end>";
				} else if ($alertTime == 420) {
					$alert->message = $this->settingManager->get('gauntlet_color')."Vizaresh <highlight>SPAWNED (7 min left)<end>!<end>";
				} else if ($alertTime == (61620-$portaltime)) {
					$alert->message = $this->settingManager->get('gauntlet_color')."Portal is <highlight>GONE<end>!<end>";
				} else if ($alertTime > (61620-$portaltime)) {
					$alert->message = $this->settingManager->get('gauntlet_color')."Portal is open for <red>".$this->util->unixtimeToReadable($alertTime)."<end>!<end>";
				} else {
					$alert->message = $this->settingManager->get('gauntlet_color')."Gauntlet is in <highlight>".$this->util->unixtimeToReadable($alertTime)."<end>!<end>";
				}
				$alerts []= $alert;
			}
		}
		$data = array();
		$data['createtime'] = $createtime;
		$data['creator'] = $creator;
		$data['repeat'] = 61620;

		//*** Add Timers
		$this->timerController->remove('Gauntlet');
		$this->timerController->add('Gauntlet', $this->chatBot->vars['name'], $this->settingManager->get('gauntlet_channels'), $alerts, "GauntletController.gauntletcallback", json_encode($data));
	}

}
