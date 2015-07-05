<?php

////////////////////////////////////////////////////////////////////////////////////////////////////////////
// CLASS NAME : PROFILING                                                                                 //
// LANGUAGE   : PHP                                                                                       //
// AUTHOR     : Julien PACHET                                                                             //
// EMAIL      : j|u|l|i|e|n| [@] |p|a|c|h|e|t|.|c|o|m                                                     //
// VERSION    : 1.1                                                                                       //
// DATE       : 11/04/2005                                                                                //
////////////////////////////////////////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////
// History:                                                                                               //
// -------                                                                                                //
//  Date        Version   Actions                                                                         //
// ------------------------------------------------------------------------------------------------------ //
//  11/04/2005  1.0       Tested & Final version                                                          //
//  31/10/2005  1.1       New function to return total time spent                                         //
////////////////////////////////////////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////
// What the class could use:                                                                              //
// * Nothing                                                                                              //
////////////////////////////////////////////////////////////////////////////////////////////////////////////
// What the class do:                                                                                     //
// * Put Label (mark) in code with a level depth                                                          //
// * Compute the time between two mark in php code, a given level                                         //
// * Show slowest part of code by a color code                                                            //
// * Can use an external data (for example number of SQL queries, to show hungriest part of SQL           //
////////////////////////////////////////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Declaration                                                                                            //
// -----------                                                                                            //
// profiling()                                                                                            //
//   add($label,$misc)                                                                                    //
//   increase()                                                                                           //
//   decrease()                                                                                           //
//   end($misc)                                                                                           //
//   get_result()                                                                                         //
//   total_time()                                                                                         //
////////////////////////////////////////////////////////////////////////////////////////////////////////////

class profiling {

	private $temps=array();
	private $niveau_actuel=0;
	public $label_divers="divers";
	private $is_end=false;
	
	/**
	* return the microtime in format sec.microsecond
	* for PHP5 compatibility
	**/
	private function _microtime() {
		return microtime(true); // php5 version of microtime!!
	}
	
	function __construct() {
		$this->add("begin","");
	}
	
	/**
	* Call when you whant to add a mark in your code
	* @param $label: the name of the label mark
	* @param $misc: optional value (for exemple the number of mysql query
	**/
	public function add($label,$misc="") {
		if ($this->is_end)
			die("Class profiling: You send to class a end() and now you want to call add($label,$misc)");
		else
			$this->temps[]=array("etiquette"=>$label,"moment"=>$this->_microtime(),"niveau"=>$this->niveau_actuel,"divers"=>$misc);
	}
	
	/**
	* Send to class that this the end of your script to profil
	**/
	public function end($misc="") {
		$this->add("end",$misc);
		$this->is_end=true;
	}
	/**
	* Increase the level var
	**/
	public function increase() {
		$this->niveau_actuel++;
	}
	/**
	* Decrease the level var
	**/
	public function decrease() {
		$this->niveau_actuel=max($this->niveau_actuel-1,0);
	}
	
	/**
	* Compute a color betwenn green and red depending of how much is $n for $total
	**/
	function _get_bgcolor($duree,$total) {
                if ($duree==0)
                        return "#FFFFFF";
                $c1="#FF0000";
                $c2="#FF7777";
                $c3="#FFCCCC";
                $c4="#77FF77";
                $c5="#CCFFCC";
                $c6="#00FF00";
                if ($duree<$total/count($this->temps)*0.125)		$c=$c4;
                elseif ($duree<$total/count($this->temps)*0.25)		$c=$c5;
                elseif ($duree<$total/count($this->temps)*0.75)		$c=$c6;
                elseif ($duree<$total/count($this->temps)*2)		$c=$c3;
                elseif ($duree<$total/count($this->temps)*3)		$c=$c2;
                else				                        $c=$c1;
                return $c;
        }
        
    /**
    * Return total time spent
    **/
  public function total_time() {
  	return $this->temps[count($this->temps)-1]['moment']-$this->temps[0]['moment'];
  }
	
	/**
	* Return the stat table of the profiling
	**/
	public function get_result() {
		/*ebug($this->temps);
		exit;*/
		$total=$this->temps[count($this->temps)-1]['moment']-$this->temps[0]['moment'];
		$res="<table>\n";
		$res.="\t<tr>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>Moment</b></td>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>Label</b></td>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>".$this->label_divers."</b></td>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>Delta ".$this->label_divers."</b></td>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>Duration</b></td>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>%</b></td>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>Duration</b></th>\n";
		$res.="\t\t<td bgcolor=#CCCCCC><b>%</b></th>\n";
		$res.="\t\t<td>&nbsp;</th>\n";
		$res.="\t</tr>\n";
		$old_divers=0;

		$max=-1;
		$delta=array();
		for ($i=0;$i<count($this->temps)-1;$i++)
			$delta[]=$this->temps[$i+1]['moment']-$this->temps[$i]['moment'];
		$max=max($delta);
		//echo $max;

		for ($i=0;$i<count($this->temps);$i++) {
			//echo $i."<br>\n";
			$l=$this->temps[$i];
			$res.="\t<tr>\n";
			$res.="\t\t<td bgcolor=\"#FFFFFF\">".date('d/m/Y H:i:s',$l['moment'])."</td>\n";
			$res.="\t\t<td bgcolor=\"#FFFFFF\">\n";
			$res.=str_repeat("&nbsp;",$l['niveau']*3);
			if (($i==0)||($i==count($this->temps)-1))
				$res.="<b>".$l['etiquette']."</b>";
			else
				$res.=$l['etiquette'];
			$res.="\t\t</td>\n";
			$res.="\t\t<td bgcolor=\"#FFFFFF\">\n".$l['divers']."</td>\n";
			$c=$this->_get_bgcolor($l['divers']-$old_divers,$this->temps[count($this->temps)-1]['divers']);
			$res.="\t\t<td bgcolor=$c>\n".($l['divers']-$old_divers)."</td>\n";
			$old_divers=$l['divers'];
			$res.="\t\t<td bgcolor=\"#FFFFFF\">\n";
			if ($i>0) {
				$duree=$l['moment']-$this->temps[$i-1]['moment'];
				$res.=(floor($duree*10000)/10)."ms";
			}
			else {
				$res.="-";
				$duree=0;
			}
			$res.="\t\t</td>\n";
		
			//echo $max;
			$c=$this->_get_bgcolor($duree,$total);
			//echo "'".$c."'";
			$p=$duree/$total*100;
			$res.="\t\t<td bgcolor=\"$c\">\n";
			$res.=floor($p)."%";
			$res.="\t\t</td>\n";
			
			// recherche debut du groupe
			$res.="\t\t<td bgcolor=\"#FFFFFF\">\n";
			if ($i>0) {
				if ($this->temps[$i]['niveau']<$this->temps[$i-1]['niveau']) {
					$j=$i-1;
					while ($this->temps[$j]['niveau']>$this->temps[$i]['niveau']) {
						$j--;
					}
				
					if ($j>0) {
						$duree=$this->temps[$i]['moment']-$this->temps[$j]['moment'];
						$res.=(floor($duree*10000)/10)."ms";
					}
					else
						$duree=0;
				}
				else
					$duree=0;
			}
			else
				$duree=0;
			$res.="\t\t</td>\n";
			$c=$this->_get_bgcolor($duree,$total);
			$res.="\t\t<td bgcolor=\"$c\">\n";
			if ($i>0)
				if ($this->temps[$i]['niveau']<$this->temps[$i-1]['niveau']) {
					$res.=floor($duree/$total*100)."%";
				}
			$res.="\t\t</td>\n";
			$res.="\t\t<td></td>\n";
			$res.="\t</tr>\n";
		}
		$res.="<tr>\n";
		$res.="\t<td colspan=\"4\"></td>\n";
		$res.="\t<td colspan=\"4\"><b>".(floor($total*10000)/10)."ms</b></td>\n";
		$res.="</tr>\n";
		$res.="</table>\n";
		return $res;
	}
}

?>
