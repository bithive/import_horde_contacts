<?php
/**
 * Import horde contacts
 *
 * Populates a new user's contacts with entries from Horde (Turba).
 *
 * Users with contacts already in Roundcube are skipped.
 *
 * You must configure your Horde database credentials in main.inc.php:
 *
 *  $rcmail_config['horde_dsn']  = 'pgsql:host=db.example.com;dbname=horde';
 *  $rcmail_config['horde_user'] = 'horde';
 *  $rcmail_config['horde_pass'] = 'password';
 *
 * See also: https://github.com/bithive/import_horde_identities
 * 
 * @version 1.1
 * @author Jason Meinzer
 *
 */
class import_horde_contacts extends rcube_plugin
{
    public $task = 'login';
    private $log = 'import_horde';

    function init()
    {
        $this->add_hook('login_after', array($this, 'fetch_turba_objects'));
    }

    function fetch_turba_objects()
    {
        $this->rc = rcmail::get_instance();
        $contacts = $this->rc->get_address_book(null, true);
        $this->load_config();

        if($contacts->count()->count > 0) return true; // exit early if user already has contacts

        $db_dsn  = $this->rc->config->get('horde_dsn');
        $db_user = $this->rc->config->get('horde_user');
        $db_pass = $this->rc->config->get('horde_pass');

        try {
            $db = new PDO($db_dsn, $db_user, $db_pass);
        } catch(PDOException $e) {
            return false;
        }

        $sth = $db->prepare('select object_firstname, object_lastname, object_email from turba_objects where owner_id = :uid');
        
        $uid = $this->rc->user->get_username();
        
        if($this->rc->config->get('horde_has_at') !== true)
        {
            list($uid) = explode('@', $uid);
        }
        
        $sth->bindParam(':uid', $uid);
        $sth->execute();

        $result = $sth->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach($result as $turba_object) {
            $record = array(
                'email'     => $turba_object['object_email'],
                'firstname' => $turba_object['object_firstname'],
                'surname'   => $turba_object['object_lastname']
            );

            if (check_email(idn_to_ascii($record['email']))) {
                $record['email'] = idn_to_utf8($record['email']);
                $contacts->insert($record, true);
                $count++;
            }
        }

        write_log($log, "Imported $count Horde contacts for $uid");
        return true;
    }
}
?>
