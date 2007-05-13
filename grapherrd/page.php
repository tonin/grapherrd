<?PHP
/******************************************************************************
 * page.php
 * Copyright Antoine Delvaux
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA,
 * or go to http://www.gnu.org/copyleft/gpl.html
 ******************************************************************************/
/******************************************************************************
 * This file control the main div containing the RRD generated graphs.
 * 
 * It's building the HTML layout and creating RRD objects and graphs from the
 * configuration object. 
 ******************************************************************************/
require("graph.php");

print "<div class=\"graphs\">\n";

if (!empty($HTTP_GET_VARS["page"])) {
  $target_cfg_file = $HTTP_GET_VARS["page"];
  $matches = preg_split("/\./", $target_cfg_file);
  $target_directory = $matches[0];
} 

if (!empty($HTTP_GET_VARS["debug"])) {
  $debug = true;
} else {
  $debug = false;
}

if (!empty($HTTP_GET_VARS["target"])) {
  $target = $HTTP_GET_VARS["target"];
  // --- style parameter
  if (!empty($HTTP_GET_VARS["style"])) {
    $style = $HTTP_GET_VARS["style"];
  } else {
    $style = $cfg->graphstyle;
  }

  if ($target != "summary") {
    // --- unique target graph

    // --- print the title and the index
    print "<h1>".$cfg->targets[$target]["title"]." graphs</h1>\n";
    if (!empty($cfg->targets[$target]["rrd"])) {
      print "<p class=\"index\">- ";
      foreach ($cfg->targets[$target]["rrd"] as $type => $rrd) {
	foreach ($cfg->t_name as $ct => $name) {
	  if (preg_match("/^".$ct."/", $type)) {
	    $cfg_type = $ct;
	  }
	}
	print "<a href=\"#".$type."\">".htmlspecialchars(str_replace("\\","",$cfg->t_name[$cfg_type]))."</a> - ";
      }
      print "</p>\n";
    }

    // --- data from MRTG cfg file
    if ($debug) {
      print "<h2>Debug mode !</h2>\n";
      print "<ul>\n";
      print "<li>URI : ".htmlspecialchars($REQUEST_URI)."</li>\n";
      print "<li>Page : ".$target_cfg_file."</li>\n";
      print "<li>Name : ".$cfg->targets[$target]["name"]."</li>\n";
      print "<li>Title : ".$cfg->targets[$target]["title"]."</li>\n";
      print "<li>Address : ";
      foreach ($cfg->targets[$target]["addresses"] as $key => $addr) {
	print $addr."  ";
      }
      print "</li>\n";
      print "<li>Interface : ";
      foreach ($cfg->targets[$target]["interfaces"] as $key => $int) {
	print $int."  ";
      }
      print "</li>\n";
      print "<li>RRD files and MaxBytes : \n<ul>\n";
      if (!empty($cfg->targets[$target]["rrd"])) {
	foreach ($cfg->targets[$target]["rrd"] as $key => $rrd) {
	  print "<li>".$key." : ".$rrd." - Max : ".$cfg->targets[$target]["maxbytes"][$key]."</li>\n";
	}
      }
      print "</ul>\n</li>\n";
      print "<li>SetEnv : \n<ul>\n";
      foreach ($cfg->targets[$target]["env"] as $trgt => $tv) {
	print "<li>".$trgt."\n<ul>\n";
	foreach ($cfg->targets[$target]["env"][$trgt] as $key => $value) {
	  print "<li>".$key." : ".$value."</li>\n";
	}
	print "</ul>\n</li>\n";
      }
      print "</ul>\n</li>\n";
      print "</ul>\n";
    }

    // --- draw target graphs
    foreach ($cfg->targets[$target]["rrd"] as $type => $rrd) {
      if ($debug) {
	print "<hr />\n";
      }
      $gr[$type] = new graph($cfg,
			     $target,
			     $debug,
			     $target_directory,
			     $type,
			     $style
			     );
      if (!empty($HTTP_GET_VARS[$type])) {
	$gr[$type]->limit = $HTTP_GET_VARS[$type];
      }
      if (!empty($HTTP_GET_VARS["nopk".$type])) {
	$gr[$type]->nopeaks = $HTTP_GET_VARS["nopk".$type];
      }

      // --- graph parameter
      if (!empty($HTTP_GET_VARS["graph"])) {
	$gr[$type]->set_period($HTTP_GET_VARS["graph"]);
      }

      print "<a name=\"".$type."\"></a>\n";
      print $gr[$type]->draw();
      print "\n";
    }
  } else {
    // --- summary graphs

    print "<h1>Summary graphs</h1>\n";
    print "<table class=\"graph\">\n";

    foreach ($cfg->targets as $key => $target) {

      // --- build graph objects
      foreach ($target["rrd"] as $type => $rrd) {
	$gr[$type] = new graph($cfg,
			       $key,
			       $debug,
			       $target_directory,
			       $type,
			       $style
			       );
	$gr[$type]->set_summary(true);

	// --- graph parameter
	if (!empty($HTTP_GET_VARS["graph"])) {
	  $gr[$type]->set_period($HTTP_GET_VARS["graph"]);
	}
      }

      // --- draw first graph (output will not be used, but draw call is needed to compute the values printed in the table)
      print "<tr>\n";
      foreach ($target["rrd"] as $type => $rrd) {
	$first = $type;
	$gr[$first]->css_tiny = $cfg->css_tiny;
	$gr[$first]->draw();
	break;
      }

      // --- insert some useful data (table)
      print "<td>\n";
      $reqstr = $cfg->buildRequestString("target", $target["name"], $HTTP_GET_VARS);
      if ( !empty($target["envtitle"]["SUBTITLE"]) ) {
	print "<h3><a href=\"grapherrd.php?$reqstr\">".$target["env"][$first]["SUBTITLE"]."</a></h3>\n";
      } else {
	print "<h3><a href=\"grapherrd.php?$reqstr\">".$target["title"]."</a></h3>\n";
      }
      print "<table class=\"summary\">\n";
      print "<tr><th>Last&nbsp;update</th> <td colspan=\"3\">".$gr[$first]->get_lastupdate()."</td></tr>\n";
      print "<tr><th>Types</th> <td colspan=\"3\">";
      $ft = true;
      foreach ($target["rrd"] as $type => $rrd) {
	if ($ft) {
	  $ft = false;
	} else {
	  print ", ";
	}
	print $gr[$type]->type;
      }
      print "</td></tr>\n";
      print "<tr><th>Limits</th> <td colspan=\"3\">";
      $ft = true;
      foreach ($target["rrd"] as $type => $rrd) {
	if ($ft) {
	  $ft = false;
	} else {
	  print ", ";
	}
	print $gr[$type]->get_bandwidth();
      }
      print "</td></tr>\n";
      print "<tr><td></td> <th class=\"top\">Max</th> <th class=\"top\">Avg</th> <th class=\"top\">Last</th> </tr>\n";
      print "<tr><th>".str_replace("\\","",$cfg->t_val_str_1[$first])."</th><td colspan=\"3\"><p class=\"summary\">".$gr[$first]->last_val_str_1."</p></td></tr>\n";
      if ($cfg->t_nb_val[$type] > 1) {
        print "<tr><th>".str_replace("\\","",$cfg->t_val_str_2[$first])."</th><td colspan=\"3\"><p class=\"summary\">".$gr[$first]->last_val_str_2."</p></td></tr>\n";
      }
      print "</table>\n";
      print "</td>\n";

      // --- really draw all graphs
      foreach ($target["rrd"] as $type => $rrd) {
	print "<td>";
	print $gr[$type]->draw();
	print "</td>\n";
      }

      print "</tr>\n";
    }
    print "</table>\n";
  }
} elseif (!empty($HTTP_GET_VARS["page"])) {
  $target_cfg_file = $HTTP_GET_VARS["page"];
  print "<h1>".$cfg->files[$target_cfg_file]." graphs</h1>";
}

print "</div>\n";

?>
