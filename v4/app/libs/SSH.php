<?php // app/SSH.php

// used for uploading files

class SSH {
    // SSH Host
    private $host = 'login.ibiblio.org',
            $port = 22,
            $user = '',
            $pass = '',
            $conn = null;
    
    public  $base_dir_local = '../',
            $base_dir_remote   = '/public/vhost/t/timeless/html/',
            $res_sftp      = null,
            $time_offset   = '+3 hours'; // Pacific (Oregon) > Eastern (North Carolina)
            
    private $max_kb = 1000;

    //=============================================================================================
   
    public function connect($config_ssh=null) {
        if (!$config_ssh) $config_ssh = Config::get('ssh');

        if (is_array($config_ssh)) {
            foreach ($config_ssh as $key => $val) {
                $this->$key = $val;
            }
        }

        if (!($this->conn = ssh2_connect($this->host, $this->port))) {
            die('Cannot connect to server');
            //throw new Exception('Cannot connect to server');
        }
        
        if (!ssh2_auth_password($this->conn, $this->user, $this->pass)) {
            die('Unable to authorize');
            //throw new Exception('Unable to authorize');
        }
        
        return $this->conn;
    } // connect()
    
    //=============================================================================================
    
    public function create_tunnel($host, $port) {
        
        if (!$this->conn) $this->connect();
        $this->tunnel = ssh2_tunnel($this->conn, $host, $port);
        return $this->tunnel;
        
    } // tunnel()
    
    //=============================================================================================
    
    public function copy($files = array()) {
        if (is_string($files)) $files = array($files);

        if (!$this->conn) $this->connect();
        if (!$this->res_sftp) $this->res_sftp = ssh2_sftp($this->conn);
        
        $output = array();

        foreach ($files as $i => $file) {
            
            $output[$i] = (object)array(
                'filename' => $file,
                'time'     => 0,
            );
            
            $time = microtime(true);
            
            $file = str_replace('\\','/',$file);
            
            $filepath_from = "{$this->base_dir_local}{$file}";
            $filepath_to   = "{$this->base_dir_remote}{$file}";
            
            $output[$i]->filesize_kb = str_pad(round(filesize($filepath_from) / 1024, 1), 3, ' ', STR_PAD_LEFT);
            
echo "<tr><td>[".($i+1)."]</td><td>{$output[$i]->filesize_kb}KB</td><td>$file</td>";
            
            if ($output[$i]->filesize_kb > $this->max_kb) {
                $output[$i]->error = 'oversize';
                echo "<br /><span style='color:red;'>Too large: $filepath_from</span>";
                continue;
            }
                
            if (!file_exists($filepath_from)) {
                $output[$i]->error = 'missing';
                echo "<br /><span style='color:red;'>Could not find: $filepath_from</span>";
                continue;
            }
            
            $mtime_from = filemtime($filepath_from);
            $mtime_to   = $this->exec("if test -e '$filepath_to'; then stat -c %Y '$filepath_to'; fi");
            
            $output[$i]->mtime = $mtime_from;
            
            if ($mtime_to > $mtime_from) {
                $output[$i]->error = 'outdated';
                echo "<br /><span style='color:red;'>Server file has been modified more recently: ". date('c', $mtime_to) ." (+". ($mtime_to - $mtime_from) ." sec)</span>";
                continue;
            }
            
            if (!$mtime_to) {
                $dirpath_to = preg_replace("'/[^/]+$'", '', $filepath_to);
                $cmd = "mkdir -p $dirpath_to";
                $output[$i]->msg = "[exec] $cmd";
echo "<td>{$output[$i]->msg}</td>";
                $this->exec($cmd);
            }
            
            $this->sftp_copy($filepath_from, $filepath_to);
            
            $output[$i]->time = (microtime(true) - $time);

echo "<td>&lt;-- time: {$output[$i]->time}</td></tr>";
        }

        return $output;
        
    } // copy()
    
    //=============================================================================================
    
    private function sftp_copy($filepath_from, $filepath_to) {
        
        set_time_limit(120);

        $fp_from = fopen($filepath_from, 'r');
        $fp_to   = fopen("ssh2.sftp://{$this->res_sftp}$filepath_to", 'w');
        
        $bytes = stream_copy_to_stream($fp_from, $fp_to);
        
        fclose($fp_from);
        fclose($fp_to);
        
        $mtime_sync = strtotime($this->time_offset, filemtime($filepath_from));
        
        $cmd = "touch -t '". date("YmdHi.s", $mtime_sync) ."' $filepath_to";
        
        return $this->exec($cmd);
        
    } // sftp_copy()
    
    //=============================================================================================
    
    public function move($file, $file_newpath) {

        if (!$this->conn) $this->connect();
        if (!$this->res_sftp) $this->res_sftp = ssh2_sftp($this->conn);
        
        $time = microtime(true);
        
        $file = str_replace('\\','/',$file);
        $file_newpath = str_replace('\\','/',$file_newpath);
        
        $filepath_from = "{$this->base_dir_remote}{$file}";
        $filepath_to   = "{$this->base_dir_remote}{$file_newpath}";
        
        //echo "<hr />" . $filepath_from ."<br />" . $filepath_to ."<hr />";

        $this->exec("mv '$filepath_from' '$filepath_to'");
        
        $filepath_from = "{$this->base_dir_local}{$file}";
        $filepath_to   = "{$this->base_dir_local}{$file_newpath}";
        
        $success = rename($filepath_from,$filepath_to);
        
        return $success;
        
    } // move()
    
    //=============================================================================================
    
    public function exec($cmd) {
        if (!$this->conn) $this->connect();
        if (!($stream = ssh2_exec($this->conn, $cmd))) {
            die('SSH command failed');
            //throw new Exception('SSH command failed');
        }
        stream_set_blocking($stream, true);
        $data = "";
        while ($buf = fread($stream, 4096)) {
            $data .= $buf;
        }
        fclose($stream);
        return $data;
    } // exec()

    //=============================================================================================
    
    public function disconnect() {
        if (!$this->conn) return;
        $this->exec('echo "EXITING" && exit;');
        $this->conn = null;
    }

    //=============================================================================================

    public function __destruct() {
        $this->disconnect();
    }

    //=============================================================================================
    
} 

