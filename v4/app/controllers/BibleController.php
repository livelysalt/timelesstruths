<?php // app/controllers/BibleController.php

class BibleController extends Controller {
    
    public
        $contact_address = "Timeless Truths Publications<br />P.O. Box 1212<br />Jefferson, OR 97352",
        $contact_email   = "mail@timelesstruths.org"; 
    
    //=============================================================================================

    public function home() {
        
        /**
         * TODO: should redirect in Url.routes.php
         */
        if (isset($_REQUEST['search'])) {
            $search = urlencode(stripslashes($_REQUEST['search']));
            header('Location: '. $this->url->base . $search);
            exit;
        }
        // ^^^

        $this->layout = 'index_page';
        $this->view->addBlock('nav',array('book_list'));
        
        $this->view->set('pageId', "home");

        $this->view->set('pageTitle', "Bible &bull; KJV study tools with Strong's definitions and audio");
        
        $this->view->set('bookList', $this->Bible->getBookList());
        
    } // home()
    
    //=============================================================================================
    
    public function search($params=array('query'=>'', 'start'=>'', 'show'=>'')) {

        /**
         * TODO: should redirect in Url.routes.php
         */
        if (isset($_REQUEST['search'])) {
            $search = urlencode(stripslashes($_REQUEST['search']));
            header('Location: '. $this->url->base . $search);
            exit;
        }
        // ^^^

        $this->layout = 'search_page';

        $search = array(
            'input'   => $params['query'],
            'query'   => $params['query'],
            'passage' => '',
            'strongs' => '',
            'word'    => '',
            'start'   => $params['start'] ? $params['start'] : $this->Bible->defaults['start'],
            'show'    => $params['show'] ? $params['show'] : $this->Bible->defaults['show']
        );

        if (preg_match("'^(.+?) in:(.+)$'", $search['query'], $m)) {
            $search['passage'] = $m[2];
            $search['query'] = $m[1];
        }

        // tests for book search
        if (preg_match("/^(?:(?:the )?book(?: of\:?|:))\s*(.+)/i", $search['query'], $m)) {
            $search['book'] = $m[1];
            
            $book = $this->Bible->doBook($search);
            
            $this->layout = 'index_page';
            $this->view->blocks['index']->elements = array('chapter_list','errors');

            $this->view->set('search',    $search /* array */);
            $this->view->set('pageTitle', "Book of {$search['book']} &mdash; Bible");
            $this->view->set('book',      $book);

        // tests for Strongs definition search
        } else if (preg_match("/^(?:strong[s]?:)?([Hh]|[Gg])[0]?(\d+)(?: \"([^\"]+)\")?/", $search['query'], $m)) {

            $search['strongs'] = $m[1].$m[2];
            $search['word'] = isset($m[3]) ? $m[3] : '';
            $this->Bible->doStrongs($search['strongs'], $search);
            
            $this->view->blocks['nav_top']->insertElementAt('strongs',1);

            $this->view->set('strongs',          $this->Bible->get('strongs'));

            $this->view->set('search',           $search /* array */);
            $this->view->set('defaults',         $this->Bible->defaults);
            $this->view->set('pageTitle',        "{$params['query']} &mdash; Bible");
            $this->view->set('lookupType',       $this->Bible->get('lookupType'));
            $this->view->set('verses',           $this->Bible->get('verses'));
            $this->view->set('totalResults',     $this->Bible->get('totalResults'));

        } else {

            $this->Bible->doQuery($search);
            
            $s = $this->Bible->get('s');
            $search['currentChapter'] = $s['currentChapter'];
            $search['book']           = $s['book'];

            $this->view->set('search',           $search /* array */);
            $this->view->set('defaults',         $this->Bible->defaults);
            $this->view->set('pageTitle',        "{$params['query']} &mdash; Bible");
            $this->view->set('lookupType',       $this->Bible->get('lookupType'));
            $this->view->set('verses',           $this->Bible->get('verses'));
            $this->view->set('totalResults',     $this->Bible->get('totalResults'));
            $this->view->set('totalChapters',    $this->Bible->get('totalChapters'));
            
        }
        
        $this->view->set('bookList',         $this->Bible->getBookList());

        $errors = $this->Bible->get('errors');

        if (count($errors)) {
            $this->view->blocks['main']->insertElementAfter('errors','verses');
            $this->view->set('errors', $errors);
        }
        
    } // search()

    //=============================================================================================
    
    public function contact() {

        $this->layout = 'info_page';

        $this->view->blocks['main']->elements = array('contact');
        
        if (($message = $_REQUEST['c-message'])) {
            
            preg_match("/^.{0,75}(?= |$)/", $message, $m);
            
            $subject = $m[0];
            if (strlen($subject) < strlen($message)) {
                $subject .= '...';
            }
            
            $email = new Email();
            $args = array(
                'to'      => $this->contact_email,
                'email'   => $_REQUEST['c-email'],
                'name'    => $_REQUEST['c-name'],
                'subject' => $subject,
                'message' => $message
            );
            $this->view->set('email_from_name',  $args['name']);
            $this->view->set('email_from_email', $args['email']);
            $this->view->set('email_message',    $args['message']);

            if ($args['name'] && !$args['email']) {
                $args['name'] .= ' [NO-REPLY]';
            }
            if (!$args['name']) {
                $args['name']  = ($args['email'] ? $args['email'] : 'anonymous');
            }
            if (!$args['email']) {
                $args['email'] = 'mail@timelesstruths.org';
            }
            $success = $email->send($args);
            
            $this->view->set('email_is_submitted', true);
            $this->view->set('email_is_success', $success);
        }
        
        $this->view->set('pageId',     "contact");
        
        $this->view->set('tt_address', $this->contact_address);
        
        $this->view->set('pageTitle',  "Bible &raquo; Contact");
        
        $this->view->set('bookList',   $this->Bible->getBookList());
        
    } // contact()

    //=============================================================================================
    
    public function dev() {
        
        phpinfo();
        
        setcookie('dev',true);
        return;
        /*
        $db = Db::get();
        
        $res = $db->query("SELECT book_id,chapter,verse FROM bible_verses");
        
        $b = $c = $v = 0;
        while ($r = $res->fetch_object()) {
            //print_r($r);
            if ($r->verse < $v) {
                echo "<br>{$b}.{$c}.{$v}";
                $db->query("INSERT INTO bible_chapters VALUES($b, $c, $v)");
                $b = $c = $v = 0;
            }
            
            $b = $r->book_id;
            $c = $r->chapter;
            $v = $r->verse;
        }
        * 
        */
    } // dev()

    //=============================================================================================

} // BibleController{}
