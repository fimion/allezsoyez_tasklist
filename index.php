<?php

	//********************** begin setup

	// error maker function
	function err($db) {
		print_r($db->errorInfo());
		echo "</br></br>";
	}

  function show_lists($theselists){
    if($theselists){
      echo "</br></br>";
      foreach($theselists as $l) {
        echo "<a href=\"./?mode=viewlist&listid=$l[0]\">$l[1]</a>";
        // buttons
        echo " | <a href=\"?mode=editlist&listid=$l[0]\">Edit Name</a> | <a href=\"?mode=deletelist&listid=$l[0]\">Delete</a></br>";
      }
    }
    else {
  		echo "</br></br>There aren't any lists yet.</br>";
  	}
    return 0;
  }


  function show_items($db,$listid){
    $statement = $db->prepare("select id, item, complete, whichlist from items where whichlist = ?");

    // is this even a good list?
    if ($statement->execute(array($listid))) {
      $theseitems = $statement->fetchAll();

      // part of navbar
      echo " | <a href=\"./?mode=additem&listid=$listid\">Add To This List</a>";

      // get list name
      $statement = $db->prepare("select listname from lists where id = ?");
      $statement->execute(array($listid));
      $name = $statement->fetch();

      if ($name['listname'] != "") {
        echo "</br></br>Viewing List \"".$name["listname"]."\"</br>";
      }

      // check for items on list
      if ($theseitems && !$itemid) {
        foreach($theseitems as $item) {
          // check status
          if (!$item[2]) {
            $checked = "☐";
            $complete = "Complete";
          }
          else {
            $checked = "☑";
            $complete = "Not Complete";
          }

          // output
          echo "$checked $item[1]";

          // buttons
          echo " |  <a href=\"?mode=checkitem&itemid=$item[0]\">Mark $complete</a> | <a href=\"?mode=edititem&itemid=$item[0]\">Edit Name</a> | <a href=\"?mode=deleteitem&itemid=$item[0]\">Delete</a></br>";
        }
      }
      else {
        if ($name['listname'] != "") {
          echo "</br>There's nothing on this list yet.</br>";
        }
        else {
          if (!$itemid) {
            echo "</br></br>This isn't a valid list.";
          }
        }
      }
    }
    else {
      echo "</br>That's not a good list ID.</br>";
    }
    return 0;
  }

	// unicooooode
	mb_internal_encoding("UTF-8");
	mb_http_output("UTF-8");
	ob_start("mb_output_handler");

	// get data
	@$mode = $_GET['mode'];
	@$listid = (int)$_GET['listid'];
	@$itemid = (int)$_GET['itemid'];

	// set our navbar here, but we'll add more shit to it depending on what mode we're in
	$navbar = "
		<a href=\".\">Home</a> | <a href=\"./?mode=addlist\">Create New List</a>
	";

	// check for database
	if ( !$db = new PDO("sqlite:listerdata.db") ){
		echo "Couldn't load database.</br>";
		err($db);
	}
	else {
		echo "Database loaded.</br>";
	}

	// i guess foreign keys aren't on by default wtf
	$db->query("PRAGMA foreign_keys = ON");

	// create the tables if they don't exist
	// list of lists
	if ( !$db->query("create table if not exists lists(id integer primary key, listname text)") ) {
		echo "Couldn't make list of lists.</br>";
		err($db);
	}
	else {
		echo "List of lists OK.</br>";
	}
	// list of items
	if ( !$db->query("create table if not exists items(id integer primary key, item text, complete integer, whichlist integer, foreign key(whichlist) references lists(id) on delete cascade)") ) {
		echo "Couldn't create list of items.</br>";
		err($db);
		}
	else {
		echo "List of items OK.</br>";
	}

	// gather our list(s)
	$lists = $db->query("select id, listname from lists");
	$theselists = $lists->fetchAll();

	//********************** begin list of lists output

	echo $navbar;
  	//***** check for our lists
  switch($mode){
    case "viewlist":
      show_items($db,$listid);
      break;

    case "addlist":
      show_lists($theselists);
      echo "
  			<form method=\"post\">
  			<input type=\"text\" name=\"addlist\">
  			<input type=\"submit\" value=\"Add List\">
  			</form>
  		";

  		@$newlist = htmlspecialchars($_POST['addlist']);

  		//attempt to insert, but only if you actually tried to do so
  		if (!$newlist) {
  			echo "";
  		}
  		else {
  			// prepare statement then run it
  			$statement = $db->prepare("insert into lists(listname) values(?)");
  			if ( !($statement->execute(array($newlist))) ) {
  				echo "There was an issue adding the new list:</br>";
  				err($db);
  			}
  			else {
  				echo "New list added: \"".$newlist."\"";
  			}
  		}
      break;

    case "editlist":
      show_lists($theselists);
      $statement = $db->prepare("select listname from lists where id=?");
  		$statement->execute(array($listid));

  		if ($listid && $thislist = $statement->fetch()) {
  			echo "Currently editing list name: \"$thislist[0]\"";

  			// text box to change name
  			echo "
  				<form method=\"post\">
  					<input type=\"text\" name=\"editlist\">
  					<input type=\"submit\" value=\"Save Change\">
  				</form>
  			";

  			// if the value is not blank, commit changes. well, try to, anyway
  			@$newedit = SQLite3::escapeString(htmlspecialchars($_POST['editlist']));

  			if (!$newedit) {
  				echo "";
  			}
  			else {
  				$statement = $db->prepare("update lists set listname = ? where id = ?");
  				if ( !$statement->execute(array($newedit,$listid)) ) {
  					echo "Something went wrong while changing that:</br>";
  					err($db);
  				}
  				else {
  					echo "List \"$thislist[0]\" renamed to \"$newedit\"";
  				}
  			}
  		}
  		else {
  			echo "Something went wrong while fetching data:</br>";
  			err($db);
  		}
      break;

    case "deletelist":
      show_lists($theselists);
      // these are the items
  		$statement = $db->prepare("select item from items where whichlist=?");
  		$statement->execute(array($listid));

  		// now for the list
  		$statement2 = $db->prepare("select listname from lists where id=?");
  		$statement2->execute(array($listid));

  		// here's a form
  		if ($listid && ($thislist = $statement2->fetch()) ) {
  			$theseitems = $statement->fetchAll();

  			echo "</br>Are you sure you wish to delete list \"$thislist[0]\"? Everything on it will be deleted too.";

  			echo "
  				<form method=\"post\">
  					<input type=\"submit\" name=\"submit\" value=\"Yes, Delete List\">
  				</form>
  			";

  			// delete the items
  			if ( isset($_POST['submit']) ) {
  				foreach($theseitems as $ti) {
  					if ( $db->query("delete from items where whichlist = $listid") ) {
  						echo "</br>Item \"$ti[0]\" deleted successfully.";
  					}
  					else {
  						echo "</br></br>Something went wrong while deleting data:</br>";
  						err($db);
  					}
  				}

  				// delete list
  				if ( $db->query("delete from lists where id = $listid") ) {
  					echo "</br>List \"$thislist[0]\" deleted successfully.";
  				}
  				else {
  					echo "</br></br>Something went wrong while deleting list:</br>";
  					err($db);
  				}
  			}
  		}
  		else {
  			echo "</br></br>Something went wrong while fetching data:</br>";
  			err($db);
  		}
      break;

    case "additem":
      show_items($db,$listid);
      // here let's have a text box for the new list item, then
  		// a submit button that will append it to the list

  		echo "
  			<form method=\"post\">
  				<input type=\"text\" name=\"addtask\">
  				<input type=\"submit\" value=\"Add Task\">
  			</form>
  		";

  		@$newtask = htmlspecialchars($_POST['addtask']);
  		// the default value for tasks being complete is 0

  		//attempt to insert, but only if you actually tried to do so
  		if (!$newtask) {
  			echo "";
  		}
  		else {
  			// prepare statement then run it
  			$statement = $db->prepare("insert into items(item, complete, whichlist) values(?,?,?)");
  			if ( !($statement->execute(array($newtask, 0, $listid))) ) {
  				echo "There was an issue adding the new task:</br>";
  				err($db);
  			}
  			else {
  				echo "New task added: \"".$newtask."\"";
  			}
  		}
      break;

    case "edititem":
      show_items($db,$listid);
      $statement = $db->prepare("select item from items where id=?");
  		$statement->execute(array($itemid));

  		if ($itemid && $thisitem = $statement->fetch()) {
  			echo "</br></br>Currently editing task: \"$thisitem[0]\"";

  			// text box to insert new item
  			echo "
  				<form method=\"post\">
  					<input type=\"text\" name=\"edittask\">
  					<input type=\"submit\" value=\"Save Change\">
  				</form>
  			";

  			// if the value is not blank, commit changes. well, try to, anyway
  			@$newedit = htmlspecialchars($_POST['edittask']);

  			if (!$newedit) {
  				echo "";
  			}
  			else {
  				$statement = $db->prepare("update items set item = ?, complete = ? where id = ?");
  				if ( !$statement->execute(array($newedit,0,$itemid)) ) {
  					echo "Something went wrong while changing that:</br>";
  					err($db);
  				}
  				else {
  					echo "Task \"$thisitem[0]\" successfully changed to \"$newedit\"";
  				}
  			}
  		}
  		else {
  			echo "</br></br>Something went wrong while fetching data:</br>";
  			err($db);
  		}
      break;

    case "deleteitem":
      show_items($db,$listid);
      $statement = $db->prepare("select item from items where id=?");
  		$statement->execute(array($itemid));

  		if ($itemid && $thisitem = $statement->fetch()) {
  			echo "</br></br>Are you sure you wish to delete task \"$thisitem[0]\"?";

  			echo "
  				<form method=\"post\">
  					<input type=\"submit\" name=\"submit\" value=\"Yes, Delete Task\">
  				</form>
  			";

  			// if deleted, say so, if not throw error
  			if ( isset($_POST['submit']) ) {
  				if ( $db->query("delete from items where id = $itemid") ) {
  					echo "</br></br>Task \"$thisitem[0]\" deleted successfully.";
  				}
  				else {
  					echo "</br></br>Something went wrong while deleting data:</br>";
  					err($db);
  				}
  			}
  		}
  		else {
  			echo "</br></br>Something went wrong while fetching data:</br>";
  			err($db);
  		}
      break;
    case "checkitem":
      show_items($db,$listid);
      $statement = $db->prepare("select item, complete from items where id=?");
  		$statement->execute(array($itemid));

  		if ($itemid && $thisitem = $statement->fetch()) {
  			// set new value
  			if ($thisitem[1] == 0) {
  				$ischecked = 1;
  				$text = "complete.";
  			}
  			else if ($thisitem[1] == 1) {
  				$ischecked = 0;
  				$text = "not complete.";
  			}
  			else {
  				echo "Something went wrong while fetching data:</br>";
  				print_r($db->errorInfo());
  			}

  			// now let's try to update it
  			$statement = $db->prepare("update items set complete = ? where id = ?");

  			if ( $statement->execute(array($ischecked,$itemid)) ) {
  				echo "</br></br>Task \"$thisitem[0]\" marked as $text";
  			}
  			else {
  				echo "</br></br>Something went wrong while updating data:</br>";
  				print_r($db->errorInfo());
  			}
  		}
  		else {
  			echo "</br></br>Something went wrong while fetching data:</br>";
  			print_r($db->errorInfo());
  		}
      break;
    default:
      show_lists($theselists);
      break;
  }
?>
