<?php
function generateToken(){
    $seed = openssl_random_pseudo_bytes(16);
    $token = bin2hex($seed);
    return $token;
}

function getCurrentPage(){
    $current = array();
    $current["url"] = basename($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    $current["page"] = parse_url($current["url"])["path"];
    $current["file"] = basename($_SERVER['PHP_SELF']);
    $current["request"] = basename($_SERVER['REQUEST_URI']);
    return $current;
}

function getPageTitle($connection,$table){
    $current = getCurrentPage();
    $currentpage = basename($current["file"]);
    $query = "SELECT pagetitle,id FROM $table WHERE link='$currentpage'";
    $result = $connection->query($query);
    if($result->num_rows>0){
        while($row = $result->fetch_assoc()){
            $pagetitle = $row["pagetitle"];
            //set session variable with current page id
            $_SESSION["page_id"]=$row["id"];
            return $pagetitle;
        }
    }
    else{
        return "Page";
        //exit();
    }
}
//this function depends on the pageid session variable set in the getPageTitle() function above
function getPageContent($dbconnection){
    //get content for this page
    $pagecontent = array();
    $currentpageid = $_SESSION["page_id"];
    $query= "SELECT * FROM sitecontent WHERE pageid='$currentpageid' AND published=1";
    $result = $dbconnection->query($query);
    if($result->num_rows>0){
        $content = array();
        while($row=$result->fetch_assoc()){
          $content["id"] = $row["id"];
          $content["title"] = $row["title"];
          $content["excerpt"] = $row["excerpt"];
          $content["content"] = $row["content"];
          array_push($pagecontent,$content);
        }
        return $pagecontent;
    }
    
}
//render content text for each page
function renderPageContent($pagecontent,$index,$element){
    //count how many pieces of content available
    switch($element){
        case "title":
            $text = "<h2>$pagecontent[$index]['title']";
            break;
        case "excerpt":
            $text = "<p>$pagecontent[$index]['excerpt']</p>";
            break;
        case "content":
            $text = "<p>$pagecontent[$index]['content']</p>";
            break;
        default:
            break;
    }
    return $text;
}
//this function returns navigation items in a ul without navclass
function getNavigationItemsAjax($startlevel,$connection,$table,$group){
    $level=$startlevel;
    $navigation = array();
    //table name is defined in head.php
    $query = "SELECT * FROM `$table` 
    WHERE level='$level' 
    AND pagesgroup='$group' 
    AND published=1 
    ORDER BY showorder ASC";
    $navresult = $connection->query($query);
    if($navresult->num_rows>0){
        while($row = $navresult->fetch_assoc()){
            //the right hand side is the names of database columns
            $id = $row["id"];
            $name = $row["name"];
            $link = $row["link"];
            $pagetitle = $row["pagetitle"];
            $icon = $row["icon_name"];
            $showicon = $row["show_icon"];
            $showname = $row["show_name"];
            $needlogin = $row["needlogin"];
            $admin = $row["admin"];
            //get classes for the link
            if($needlogin==0 && $link!="login.php" && $link!="register.php"){
                $class = addClases($link);
                //if class is active (ie we are currently on this page)
                if($class=="active"){
                    $menuitem = "<li class=\"$class\">".
                    addIconToLink($link,$name,$showname,$showicon,$icon);
                }
                //if the item has a dropdown, add these extra attributes (part of bootstrap dropdown menu)
                elseif($class=="dropdown" || $class=="active dropdown"){
                    $menuitem = "<li class=\"$class\">
                    <a class=\"dropdown-toggle\" 
                    data-toggle=\"dropdown\" href=\"$link\">$name
                    <span class=\"caret\"></span></a>";
                }
                //otherwise just render as normal menu item
                else{
                    $menuitem = "<li>".addIconToLink($link,$name,$showname,$showicon,$icon);
                }
                array_push($navigation,$menuitem);
                //get the submenu, if any
                if($link == "#" || $link == ""){
                    $dropdownmenu = getSubMenu($id,$connection,$table);
                    if($dropdownmenu){
                        array_push($navigation,implode("",$dropdownmenu));
                    }
                }
                //close the navigation list item
                array_push($navigation,"</li>");
            }
            //only add these menuitem if user is logged in
            elseif($needlogin && $_SESSION["userid"] && $admin==0){
                $menuitem = "<li>".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
            elseif($needlogin && $admin && $_SESSION["userid"] && $_SESSION["admin"]){
                $menuitem = "<li>".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
            elseif($link=="login.php" && !$_SESSION["userid"]){
                $menuitem = "<li>".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
            elseif($link=="register.php" && !$_SESSION["userid"]){
                $menuitem = "<li>".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
            elseif($link=="shoppingcart.php"){
                $menuitem = "<li class=\"shoppingcart $class\">".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
        }
        //return the navigation elements
        return $navigation;
        //close the database connection (saves memory)
        $connection->close();
    }
}

function getNavigationItems($startlevel,$connection,$table,$group,$navclass=""){
    $level=$startlevel;
    $navigation = array();
    //table name is defined in head.php
    $query = "SELECT * FROM `$table` 
    WHERE level='$level' 
    AND pagesgroup='$group' 
    AND published=1 
    ORDER BY showorder ASC";
    $navresult = $connection->query($query);
    if($navresult->num_rows>0){
        $navstart = "<ul class=\"nav navbar-nav $navclass\">";
        array_push($navigation,$navstart);
        while($row = $navresult->fetch_assoc()){
            //the right hand side is the names of database columns
            $id = $row["id"];
            $name = $row["name"];
            $link = $row["link"];
            $pagetitle = $row["pagetitle"];
            $icon = $row["icon_name"];
            $showicon = $row["show_icon"];
            $showname = $row["show_name"];
            $needlogin = $row["needlogin"];
            $admin = $row["admin"];
            //get classes for the link
            $class = addClases($link);
            if($needlogin==0 && $link!="login.php" && $link!="register.php"){
                
                //if class is active (ie we are currently on this page)
                if($class=="active"){
                    $menuitem = "<li class=\"$class\">".
                    addIconToLink($link,$name,$showname,$showicon,$icon);
                }
                //if the item has a dropdown, add these extra attributes (part of bootstrap dropdown menu)
                elseif($class=="dropdown" || $class=="active dropdown"){
                    $menuitem = "<li class=\"$class\">
                    <a class=\"dropdown-toggle\" 
                    data-toggle=\"dropdown\" href=\"$link\">$name
                    <span class=\"caret\"></span></a>";
                }
                //otherwise just render as normal menu item
                else{
                    $menuitem = "<li>".addIconToLink($link,$name,$showname,$showicon,$icon);
                }
                array_push($navigation,$menuitem);
                //get the submenu, if any
                if($link == "#" || $link == ""){
                    $dropdownmenu = getSubMenu($id,$connection,$table);
                    if($dropdownmenu){
                        array_push($navigation,implode("",$dropdownmenu));
                    }
                }
                //close the navigation list item
                array_push($navigation,"</li>");
            }
            //only add these menuitem if user is logged in
            elseif($needlogin && $_SESSION["userid"] && $admin==0){
                $menuitem = "<li class=\"$class\">".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
            //if page requires login and admin privilege and user is logged in and is an admin
            elseif($needlogin && $admin && $_SESSION["userid"] && $_SESSION["admin"]){
                $menuitem = "<li class=\"$class\">".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
            //if the nav item is login.php and user is not logged in
            elseif($link=="login.php" && !$_SESSION["userid"]){
                $menuitem = "<li class=\"$class\">".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
            elseif($link=="register.php" && !$_SESSION["userid"]){
                $menuitem = "<li class=\"$class\">".addIconToLink($link,$name,$showname,$showicon,$icon)."</li>";
                array_push($navigation,$menuitem);
            }
        }
        //return the navigation elements
        return $navigation;
        //close the database connection (saves memory)
        $connection->close();
    }
}

function addClases($link){
    //get the current URL or page file name
    $current = getCurrentPage();
    $classes = array();
    if($link==$current["url"] || $link==$current["page"] || $link==$current["file"]){
        array_push($classes,"active");
    }
    elseif($link=="register.php"){
        array_push($classes,"shopping-cart");
    }
    elseif($link=="#" || $link==""){
        array_push($classes,"dropdown");
    }
    return implode(" ",$classes);
}

function getSubMenu($parent,$connection,$table){
    $subnavigation = array();
    $query = "SELECT * FROM pages WHERE parentid='$parent' ORDER BY 'order' ASC";
    $result = $connection->query($query);
    if($result->num_rows>0){
        //add the open tags of subnavigation
        $submenu = "<ul class=\"dropdown-menu\">";
        array_push($subnavigation,$submenu);
        while($row = $result->fetch_assoc()){
            $name = $row["name"];
            $link = $row["link"];
            $class = implode(" ",addClases($link));
            if($class){
                $submenuitem = "<li class=\"$class\">
                <a href=\"$link\">$name</a>
                </li>";
            }
            else{
                $submenuitem = "<li><a href=\"$link\">$name</a></li>";
            }
            array_push($subnavigation,$submenuitem);
        }
        //at the end of sub menu items, close the submenu <ul> tag
        array_push($subnavigation,"</ul>");
       
    }
    else{
        array_push($subnavigation,"<!--empty-->");
    }
    return $subnavigation;
    $connection->close();
}

function addIconToLink($link,$name,$showname,$showicon,$icon){
    if($showicon && $showname){
        if($icon){
            $linkwithicon = "<a href=\"$link\"><i class=\"$icon\"></i>$name";
        }
    }
    elseif($showicon && !$showname){
        if($icon){
            $linkwithicon = "<a href=\"$link\"><i class=\"$icon\"></i>";
        }
    }
    elseif(!$showicon && $showname){
        $linkwithicon = "<a href=\"$link\">$name";
    }
    
    //close link
    $linkwithicon = $linkwithicon."<span class=\"badge\"></span></a>";
    return $linkwithicon;
}

function getUserName($userid,$connection){
   $query = "SELECT email,username FROM users WHERE userid='$userid'";
   $result = $connection->query($query);
   if($result->num_rows > 0){
      $row = $result->fetch_assoc();
      $username = $row["username"];
      $email = $row["email"];
      //if no username, return the email address
      if(!$username){
         return $email;
      }
      else{
         return $username;
      }
   }
}

//check if the user is admin
function checkAdmin($userid,$connection){
   $query = "SELECT userid,admin FROM users WHERE userid='$userid'";
   $result = $connection->query($query);
   if($result->num_rows > 0){
      $row = $result->fetch_assoc();
      $id = $row["userid"];
      $admin = $row["admin"];
      if($id===$userid){
         $connection->close();
         return true;
      }
      else{
         $connection->close();
         return false;
      }
   }
}

//get products from products table
function getProduct($connection,$id){
    $query = "SELECT id,sku,stocklevel,name,description,sellprice,special,saleprice 
    FROM products WHERE published=true AND id='$id'";
    $result = $connection->query($query);
    if($result->num_rows >0){
        $row = $result->fetch_assoc();
        return($row);
        $connection->close();
    }
}

function getProductImages($connection,$productid){
    $query = "SELECT title,imagefile,caption FROM images WHERE productid='$productid' AND published=true";
    $result = $connection->query($query);
    $images = array();
    if($result->num_rows > 0){
        while($row = $result->fetch_assoc()){
            $image = array();
            $image["id"] = $row["id"];
            $image["title"] = $row["title"];
            $image["caption"] = $row["caption"];
            $image["file"] = $row["imagefile"];
            array_push($images,$image);
        }
        return $images;
        $connection->close();
    }
    
}

function getProductFilter($connection,$filter){
    switch($filter){
        case "colour":
            $table = "color";
            break;
        case "categories":
            $table = "categories";
            break;
        case "brands":
            $table = "brands";
            break;
        default:
            break;
    }
    $query = "SELECT id,name FROM $table";
    $result = $connection->query($query);
    $categories = array();
    $all = array("id"=>0,"name"=>"all $table");
    array_push($categories,$all);
    if($result->num_rows > 0){
        while($row = $result->fetch_assoc()){
            array_push($categories,$row);
        }
        return $categories;
    }
    $connection->close();
}
//generate a date and time
function generateDateTime(){
    $date = new DateTime("now", new DateTimeZone('Australia/Sydney') );
    $date = $date->format("Y-m-d H:i:s");
    return $date;
}
//function to log user activity, updated every page load
function logActivity($connection){
    //if user is logged in
    if($_SESSION["userid"]){
        $userid = $_SESSION["userid"];
        $date = generateDateTime();
        $query = "UPDATE users SET lastactivity='$date' WHERE userid='$userid'";
        $connection->query($query);
    }
}

//this function unsets user session (log user out) after a certain amount of time
// has lapsed since last activity
function checkSessionAge($connection,$maxtimelapsed,$returnlapsed){
    //if user is logged in
    if($_SESSION["userid"]){
        $userid = $_SESSION["userid"];
        //generate date time for now
        $now = generateDateTime();
        $query = "SELECT lastactivity FROM users WHERE userid='$userid'";
        $result = $connection->query($query);
        if($result->num_rows>0){
            $row = $result->fetch_assoc();
            $last = $row["lastactivity"];
            //convert last activity and now to time using strtotime()
            $lapsed = strtotime($now) - strtotime($last);
            if($lapsed>$maxtimelapsed){
                if($returnlapsed){
                    return $lapsed;
                }
                else{
                    return true;
                }
            }
            else{
                if($returnlapsed){
                    return $lapsed;
                }
                else{
                    return false;
                }
            }
        }
    }
}

function generateUserId(){
    $prefix = "user";
    $userid = md5(uniqid($prefix,TRUE));
    return $userid;
}

function logOutUser(){
    //if user is not admin
    if(!$_SESSION["admin"]){
       unset($_SESSION["userid"]);
       unset($_SESSION["email"]);
       unset($_SESSION["cart"]);
    }
    //if user is admin
    else{
       unset($_SESSION["userid"]);
       unset($_SESSION["email"]);
       unset($_SESSION["admin"]);
       unset($_SESSION["cart"]);
    }
    session_destroy();
}


?>