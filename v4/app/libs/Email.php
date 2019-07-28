<?php // app/Email.php

class Email {
    
    private static $default_args = array(
        'to'      => 'webmaster@timelesstruths.org',
        'email'   => 'webmaster@timelesstruths.org',
        'subject' => '[system message]',
    );
    
    public static function send($args=array()){
        
        if (is_string($args)) $args = array('message' => $args);
        
        $args        = array_merge(self::$default_args,$_POST,$args);
        
        $to          = $args['to']; 
        $email       = $args['email']; 
        $name        = $args['name'];
        $subject     = $args['subject']; 
        $message     = $args['message'];
        
        $To          = strip_tags($to);
        $HTMLMessage = nl2br(stripslashes($message));
        $TextMessage = strip_tags($HTMLMessage,"<br>");
        $FromName    = strip_tags($name);
        $FromEmail   = strip_tags($email);
        $Subject     = trim(strip_tags(stripslashes($subject)));

        $charset     = 'iso-8859-1';
        
        $boundary1   = rand(0,9)."-"
            .rand(10000000000,9999999999)."-"
            .rand(10000000000,9999999999)."=:"
            .rand(10000,99999);
        $boundary2   = rand(0,9)."-".rand(10000000000,9999999999)."-"
            .rand(10000000000,9999999999)."=:"
            .rand(10000,99999);
        
         
        for ($i=0; $i < count($_FILES['youfile']['name']); $i++) {
            if (is_uploaded_file($_FILES['fileatt']['tmp_name'][$i]) && 
               !empty($_FILES['fileatt']['size'][$i]) && 
               !empty($_FILES['fileatt']['name'][$i])) {
                     
                $attach       = 'yes';
                $end          = '';
                
                $handle       = fopen($_FILES['fileatt']['tmp_name'][$i], 'rb'); 
                $f_contents   = fread($handle, $_FILES['fileatt']['size'][$i]); 
                $attachment[] = chunk_split(base64_encode($f_contents));
                fclose($handle); 
                
                $ftype[]      = $_FILES['fileatt']['type'][$i];
                $fname[]      = $_FILES['fileatt']['name'][$i];
            }
        }
        
        /***************************************************************
         Creating Email: Headers, BODY
         1- HTML Email Without Attachment!! <<-------- H T M L ---------
         ***************************************************************/
#---->Headers Part
$Headers     =<<<AKAM
From: $FromName <$FromEmail>
Reply-To: $FromEmail
MIME-Version: 1.0
Content-Type: multipart/alternative;
    boundary="$boundary1"
AKAM;

#---->BODY Part
$Body        =<<<AKAM
MIME-Version: 1.0
Content-Type: multipart/alternative;
    boundary="$boundary1"

This is a multi-part message in MIME format.

--$boundary1
Content-Type: text/plain;
    charset="$charset"
Content-Transfer-Encoding: quoted-printable

$TextMessage
--$boundary1
Content-Type: text/html;
    charset="$charset"
Content-Transfer-Encoding: quoted-printable

$HTMLMessage

--$boundary1--
AKAM;
        
        /***************************************************************
         2- HTML Email With Multiple Attachment <<----- Attachment ------
         ***************************************************************/
         
        if ($attach=='yes') {
        
            $attachments='';
$Headers     =<<<AKAM
From: $FromName <$FromEmail>
Reply-To: $FromEmail
MIME-Version: 1.0
Content-Type: multipart/mixed;
    boundary="$boundary1"
AKAM;

for($j=0;$j<count($ftype); $j++){
$attachments.=<<<ATTA
--$boundary1
Content-Type: $ftype[$j];
    name="$fname[$i]"
Content-Transfer-Encoding: base64
Content-Disposition: attachment;
    filename="$fname[$j]"

$attachment[$j]

ATTA;
        } // END: if ($attach=='yes')
        
$Body        =<<<AKAM
This is a multi-part message in MIME format.

--$boundary1
Content-Type: multipart/alternative;
    boundary="$boundary2"

--$boundary2
Content-Type: text/plain;
    charset="$charset"
Content-Transfer-Encoding: quoted-printable

$TextMessage
--$boundary2
Content-Type: text/html;
    charset="$charset"
Content-Transfer-Encoding: quoted-printable

$HTMLMessage

--$boundary2--

$attachments
--$boundary1--
AKAM;
}
        
        /***************************************************************
         Sending Email
         ***************************************************************/

        $ok = mail($To, $Subject, $Body, $Headers);

        return $ok;
        
    } // send()

} // Email{}
###################################################################################################
