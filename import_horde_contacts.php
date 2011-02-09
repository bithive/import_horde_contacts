<?php
/**
 * Import horde contacts
 *
 * Populates a new user's contacts with entries from Horde (Turba).
 *
 * @version 1.0
 * @author Jason Meinzer
 *
 */
class import_horde_contacts extends rcube_plugin
{
    public $task = 'login';

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

	$db_dsn  = $this->rc->config->get('import_horde_contacts_dsn');
	$db_user = $this->rc->config->get('import_horde_contacts_user');
	$db_pass = $this->rc->config->get('import_horde_contacts_pass');

	try {
		$db = new PDO($db_dsn, $db_user, $db_pass);
	} catch(PDOException $e) {
		return false;
	}

	$sth = $db->prepare('select object_firstname, object_lastname, object_email from turba_objects where owner_id = :uid');
	$uid = explode('@', $this->rc->user->get_username());
        $uid = $uid[0];
	$sth->bindParam(':uid', $uid);
	$sth->execute();

	$result = $sth->fetchAll(PDO::FETCH_ASSOC);
	foreach($result as $turba_object) {
		$rec = array(
			'email'     => $turba_object['object_email'],
			'firstname' => $turba_object['object_firstname'],
			'surname'   => $turba_object['object_lastname']
                );

		if (check_email(idn_to_ascii($rec['email']))) {
			$rec['email'] = idn_to_utf8($rec['email']);
			$contacts->insert($rec, true);
		}
	}

	return true;
    }
}
?>
