<?php

/**
 * Profile modules
 * @package modules
 * @subpackage profile
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/profiles/hm-profiles.php';

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_edit_data extends Hm_Handler_Module {
    public function process() {
        $id = false;
        if (array_key_exists('profile_id', $this->request->get)) {
            $id = $this->request->get['profile_id'];
        }
        if ($id !== false) {
            $accounts = $this->get('profiles');
            if (count($accounts) > $id) {
                $this->out('edit_profile', $accounts[$id]);
                $this->out('default_email_domain', $this->config->get('default_email_domain'));
                $this->out('edit_profile_id', $id);
            }
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_compose_profile_data extends Hm_Handler_Module {
    public function process() {
        $profiles = new Hm_Profiles($this);
        $compose_profiles = array();
        foreach ($profiles->list_all() as $id => $vals) {
            if ($vals['smtp_id'] !== false && $vals['smtp_id'] !== '') {
                $compose_profiles[$vals['smtp_id']] = $vals;
            }
        }
        $this->out('compose_profiles', $compose_profiles);
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_process_profile_update extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('profile_id'));
        if ($success) {
            $smtp = false;
            $replyto = '';
            $name = '';
            $sig = '';
            $address = '';
            $default = false;
            if (array_key_exists('profile_address', $this->request->post)) {
                $address = $this->request->post['profile_address'];
            }
            if (array_key_exists('profile_smtp', $this->request->post)) {
                $smtp = $this->request->post['profile_smtp'];
            }
            if (array_key_exists('profile_replyto', $this->request->post)) {
                $replyto = $this->request->post['profile_replyto'];
            }
            if (array_key_exists('profile_name', $this->request->post)) {
                $name = $this->request->post['profile_name'];
            }
            if (array_key_exists('profile_sig', $this->request->post)) {
                $sig = $this->request->post['profile_sig'];
            }
            if (array_key_exists('profile_default', $this->request->post)) {
                $default = true;
            }
            $data = $this->get('profiles');

            if (count($data) > $form['profile_id']) {
                $current = $data[$form['profile_id']];
                $profile = array(
                    'name' => $name,
                    'sig' => $sig,
                    'smtp_id' => $smtp,
                    'replyto' => $replyto,
                    'default' => $default,
                    'address' => $address,
                    'server' => $current['server'],
                    'user' => $current['user'],
                    'type' => $current['type']
                );
                $profiles = new Hm_Profiles($this);
                if ($profiles->edit($form['profile_id'], $profile)) {
                    $this->session->record_unsaved('Profile updated');
                    if ($default) {
                        $profiles->set_default($form['profile_id']);
                    }
                    $profiles->save($this->user_config);
                    $user_data = $this->user_config->dump();
                    $this->session->set('user_data', $user_data);
                    Hm_Msgs::add('Profile Updated');
                }
            }
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_data extends Hm_Handler_Module {
    public function process() {
        $accounts = array();
        $profiles = new Hm_Profiles($this);
        $this->out('profiles', $profiles->list_all());
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_edit_form extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="profile_content"><div class="content_title">'.$this->trans('Profiles');
        $smtp_servers = $this->get('smtp_servers', array());
        if ($this->get('edit_profile')) {
            $domain = $this->get('default_email_domain');
            $profile = $this->get('edit_profile');
            $id = $this->get('edit_profile_id');
            if (!$profile['replyto']) {
                if ($domain && !is_email($profile['user'])) {
                    $profile['user'] = $profile['user'].'@'.$domain;
                }
                $profile['replyto'] = $profile['user'];
            }
            $res .= '<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" width="8" height="8" />';
            $res .= $this->html_safe($profile['name']).'</div>';
            $res .= '<div class="edit_profile"><form method="post" action="?page=profiles">';
            $res .= '<input type="hidden" name="profile_id" value="'.$this->html_safe($id).'" />';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<table><tr><th>'.$this->trans('Display Name').'</th><td><input type="text" name="profile_name" value="'.$this->html_safe($profile['name']).'" /></td></tr>';
            $res .= '<tr><th>'.$this->trans('E-mail Address').'</th><td><input type="email" name="profile_address" value="'.$this->html_safe($profile['address']).'" /></td></tr>';
            $res .= '<tr><th>'.$this->trans('Reply-to').'</th><td><input type="email" name="profile_replyto" value="'.$this->html_safe($profile['replyto']).'" /></td></tr>';
            $res .= '<tr><th>'.$this->trans('SMTP Server').'</th><td><select name="smtp_id">';
            foreach ($smtp_servers as $id => $server) {
                $res .= '<option ';
                if ($id == $profile['smtp_id']) {
                    $res .= 'selected="selected"';
                }
                $res .= 'value="'.$this->html_safe($id).'">'.$this->html_safe($server['name']).'</option>';
            }
            $res .= '</select></td></tr>';
            $res .= '<tr><th>'.$this->trans('Signature').'</th><td><textarea cols="80" rows="4" name="profile_sig">';
            $res .= $this->html_safe($profile['sig']).'</textarea></td></tr>';
            $res .= '<tr><th>'.$this->trans('Set as default').'</th><td><input type="checkbox" ';
            if ($profile['default']) {
                $res .= 'checked="checked"';
            }
            $res .= 'name="profile_default" /></td></tr>';
            $res .= '<tr><td></td><td><input type="submit" value="'.$this->trans('Update').'" /></td></tr></table>';
            $res .= '</form></div>';
        }
        else {
            $res .= '</div>';
        }
        return $res;
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$person).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Profiles').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_compose_signature_button extends Hm_Output_Module {
    protected function output() {
        return '<input class="compose_sign" type="button" value="'.$this->trans('Sign').'" />';
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_compose_signature_values extends Hm_Output_Module {
    protected function output() {
        $res = '<script type="text/javascript">var profile_signatures = {';
        $sigs = array();
        foreach ($this->get('compose_profiles', array()) as $smtp_id => $vals) {
            if (strlen(trim($vals['sig']))) {
                $sigs[] = sprintf("%s: \"\\n%s\\n\"", $smtp_id, $this->html_safe(str_replace("\r\n", "\\n", $vals['sig'])));
            }
        }
        $res .= implode(', ', $sigs).'}</script>';
        return $res;
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_content extends Hm_Output_Module {
    protected function output() {
        $profiles = $this->get('profiles');
        $res = '';
        if (count($profiles) > 0) {
            $smtp_servers = $this->get('smtp_servers', array());
            $res .= '<table class="profile_details"><tr>'.
                '<th>'.$this->trans('Name').'</th>'.
                '<th class="profile_fld">'.$this->trans('Server').'</th>'.
                '<th class="profile_fld">'.$this->trans('Username').'</th>'.
                '<th>'.$this->trans('Display Name').'</th>'.
                '<th class="profile_fld">'.$this->trans('E-mail Address').'</th>'.
                '<th class="profile_fld">'.$this->trans('Reply-to').'</th>'.
                '<th class="profile_fld">'.$this->trans('SMTP Server').'</th>'.
                '<th class="profile_fld">'.$this->trans('Signature').'</th>'.
                '<th class="profile_fld">'.$this->trans('Default').'</th>'.
                '<th></th></tr>';

            foreach ($profiles as $id => $profile) {
                $smtp = '';
                    if ($profile['smtp_id'] !== false && array_key_exists($profile['smtp_id'], $smtp_servers)) {
                        $smtp = $smtp_servers[$profile['smtp_id']]['name'];
                    }
                $res .= '<tr>'.
                    '<td>'.$this->html_safe($profile['name']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['server']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['user']).'</td>'.
                    '<td>'.$this->html_safe($profile['name']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['address']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['replyto']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($smtp).'</td>'.
                    '<td class="profile_fld">'.(strlen($profile['sig']) > 0 ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '<td class="profile_fld">'.($profile['default'] ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '<td><a href="?page=profiles&amp;profile_id='.$this->html_safe($id).'" title="'.$this->trans('Edit').'">'.
                    '<img alt="'.$this->trans('Edit').'" width="16" height="16" src="'.Hm_Image_Sources::$cog.'" /></a></td>'.
                    '</tr>';
            }
            $res .= '</table>';
        }
        else {
            $res .= '<div class="profiles_empty">'.$this->trans('No IMAP or POP3 servers configured').'</div>';
        }
        $res .= '</div>';
        return $res;
    }
}
