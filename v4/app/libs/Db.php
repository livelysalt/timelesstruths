<?php // app/Db.php

# File Description: MySQL Singleton Class to allow easy and clean access to common mysql commands
# Author: ricocheting
# Web: http://www.ricocheting.com/
# Update: 2010-07-19
# Version: 3.1.4
# Copyright 2003 ricocheting.com


/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

# Author: Joel Erickson
# Update: 2010-10-28
#    * changed functions to use MySQLi class
#    * changed connection to be made automatically at the point of first query
# Example: $db = Db::get();


class Db {

	// Store the single instance of Database
	private static $instance;

    // default local (portable) database settings
	private	$host = "localhost",
	        $user = "root",
	        $pass = "",
	       	$data = "timeless",
            $conn = 0,
            $res  = 0,
    
            $lazy = true, // change to false to connect at construction
            $table_prefix = 'tt4_',
            $table_prefix_char = '~',
            $sql = '',
	        $error = '';

	#######################
	//number of rows affected by SQL query
	public	$affected_rows = 0;


    #-#############################################
    # desc: constructor
    public function __construct($config_db=null) {
        // if not specified, load from config
        if (!$config_db) $config_db = Config::get('db');

        if (is_array($config_db)) {
            foreach ($config_db as $key => $val) {
                $this->$key = $val;
            }
        }
        
        if(!$this->lazy) {
            $this->connect();
        }
    } // __construct()
    
    public function __destruct() {
        $this->close();
    }
    
    #-#############################################
    # desc: singleton declaration
    public static function get($args=null){
    	if (!self::$instance){ 
    		self::$instance = new Db($args); 
    	} 
    	return self::$instance; 
    } // get()
    
    
    #-#############################################
    # desc: connect and select database using vars above
    public function connect(){
        $this->conn = new mysqli($this->host,$this->user,$this->pass,$this->data);
        $this->conn->set_charset('latin1');
    
    	if (!$this->conn) {//open failed
    		$this->oops("Could not connect to host: <b>$this->host</b>.");
    	}

    	// unset the data so it can't be dumped
    	$this->host = '';
    	$this->user = '';
    	$this->pass = '';
    	$this->data = '';
        
    } // connect()
    
    
    
    #-#############################################
    # desc: close the connection
    public function close(){
        if (!$this->conn) {
            return 0;
        }
    	if (!@$this->conn->close()){
    		$this->oops("Connection close failed.");
    	}
    } // close()
    
    
    #-#############################################
    # Desc: escapes characters to be mysql ready
    # Param: string
    # returns: string
    public function escape($string){
    	if (!$this->conn){ $this->connect(); }
    	if (get_magic_quotes_runtime()) $string = stripslashes($string);
    	return @$this->conn->real_escape_string($string);
    } // escape()
    
    
    #-#############################################
    # Desc: executes SQL query to an open connection
    # Param: (MySQL query) to execute
    # returns: (res) for fetching results etc
    public function query($sql){
    	if (!$this->conn){ $this->connect(); }
    
        $this->error = false;
    
        // inserts necessary prefix
        $this->sql = preg_replace("'(?<=[ `])" . $this->table_prefix_char . "(?=\w)'", $this->table_prefix, $sql);
        
    	// do query
    	$this->res = $this->conn->query($sql);
    
    	if (!$this->res){
    		$this->oops("<b>MySQL Query fail ({$this->conn->error}):</b> $sql");
    		return 0;
    	}
    	
    	$this->affected_rows = @$this->conn->affected_rows;
    
    	return $this->res;
    } // query()
    
    
    #-#############################################
    # desc: does a query, fetches the first row only, frees resultset
    # param: (MySQL query) the query to run on host
    # returns: array of fetched results
    public function queryFirst($query_string){
    	$res = $this->query($query_string);
    	$out = $this->fetch($res);
    	$this->freeResult();
    	return $out;
    } // queryFirst()
    
    
    #-#############################################
    # desc: fetches and returns results one line at a time
    # param: res for mysql run. if none specified, last used
    # return: (object) fetched record(s)
    public function fetch($res=-1){
    	// retrieve row
    	if ($res != -1){
    		$this->res = $res;
    	}
    
    	if (isset($this->res)){
    		$record = @$this->res->fetch_object();
    	} else {
    		$this->oops("Invalid query: <b>". print_r($this->res,true) ."</b>. Records could not be fetched.");
    	}
    
    	return $record;
    } // fetch()
    
    
    #-#############################################
    # desc: returns all the results (not one row)
    # param: (MySQL query) the query to run on host
    # returns: array of ALL fetched results
    public function fetchArray($sql){
    	$res = $this->query($sql);
    	$out = array();
    
    	while ($row = $this->fetch($res)){
    		$out[] = $row;
    	}
    
    	$this->freeResult();
    	return $out;
    } // fetchArray()
    
    
    #-#############################################
    # desc: does an update query with an array
    # param: table, assoc array with data (not escaped), where condition (optional. if none given, all records updated)
    # returns: (res) for fetching results etc
    public function update($table, $data, $where='1'){
    	$q = "UPDATE `$table` SET ";
    
    	foreach($data as $key=>$val){
    		if (strtolower($val) == 'null') {
    		    $q .= "`$key` = NULL, ";
            } else if (strtolower($val) == 'now()') {
                $q .= "`$key` = NOW(), ";
            } else if (preg_match("/^increment\((\-?\d+)\)$/i",$val,$m)) {
                $q .= "`$key` = `$key` + $m[1], ";
            } else {
                $q .= "`$key` = '" . $this->escape($val) . "', ";
            }
    	}
    
    	$q = rtrim($q, ', ') . ' WHERE ' . $where.';';
    
    	return $this->query($q);
    } // update()
    
    
    #-#############################################
    # desc: does an insert query with an array
    # param: table, assoc array with data (not escaped)
    # returns: id of inserted record, false if error
    public function insert($table, $data){
    	$q="INSERT INTO `$table` ";
    	$v=''; $n='';
    
    	foreach($data as $key=>$val){
    		$n.="`$key`, ";
    		if(strtolower($val)=='null') $v.="NULL, ";
    		elseif(strtolower($val)=='now()') $v.="NOW(), ";
    		else $v.= "'".$this->escape($val)."', ";
    	}
    
    	$q .= "(". rtrim($n, ', ') .") VALUES (". rtrim($v, ', ') .");";
    
    	if($this->query($q)){
    		return $this->conn->insert_id;
    	}
    	else return false;
    
    } // insert()
    
    
    #-#############################################
    # desc: frees the resultset
    # param: res for mysql run. if none specified, last used
    private function freeResult(){
        $this->res->free();
    } // freeResult()
    
    
    #-#############################################
    # desc: throw an error message
    # param: [optional] any custom error to display
    private function oops($msg=''){
    	if(!empty($this->conn)){
    		$this->error = $this->conn->error;
    	}
    	else{
    		$this->error = mysqli_error();
    		$msg="<b>WARNING:</b> No {conn} found. Likely not connected to database.<br />$msg";
    	}
    
    	// if no debug, done here
    	if(!TT_DEBUG) return;
    	?>
    		<table align="center" border="1" cellspacing="0" style="background:white;color:black;width:80%;">
    		<tr><th colspan=2>Database Error</th></tr>
    		<tr><td align="right" valign="top">Message:</td><td><?php echo $msg; ?></td></tr>
    		<?php if(!empty($this->error)) echo '<tr><td align="right" valign="top" nowrap>MySQL Error:</td><td>'.$this->error.'</td></tr>'; ?>
    		<tr><td align="right">Date:</td><td><?php echo date("l, F j, Y \a\\t g:i:s A"); ?></td></tr>
    		<?php if(!empty($_SERVER['REQUEST_URI'])) echo '<tr><td align="right">Script:</td><td><a href="'.$_SERVER['REQUEST_URI'].'">'.$_SERVER['REQUEST_URI'].'</a></td></tr>'; ?>
    		<?php if(!empty($_SERVER['HTTP_REFERER'])) echo '<tr><td align="right">Referer:</td><td><a href="'.$_SERVER['HTTP_REFERER'].'">'.$_SERVER['HTTP_REFERER'].'</a></td></tr>'; ?>
    		</table>
    	<?php
    } // oops()


} // Db{}
###################################################################################################
