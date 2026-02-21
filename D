<?php

class set_team_league_groupAction extends ActionModule
{
    protected $content = '';
    protected $subMenu = array();
    protected $Java_script = '';

    function init()
    {
        if (($_SESSION['gt']['user_rule']>=10 || empty($_SESSION['gt']['user_login']))) {
            SystemClass::setMessage_user(MESS_NO_ACCESS);
            $this->setContent('ERROR');
            return;
        }

        $turnir_id = (int)poste('turnir_id');
        $league_id = (int)poste('league_id');
        $team_id = (int)poste('team_id');
        $group_num = (int)poste('group_num');

        if ($turnir_id <= 0 || $league_id <= 0 || $team_id <= 0) {
            SystemClass::setMessage_user('Некоректні параметри для зміни ліги.');
            $this->setContent('ERROR');
            return;
        }

        $leagues_count = (int)db_field('SELECT team_leagues_count FROM `'.T_TURNIRS.'` WHERE id='.$turnir_id, 'team_leagues_count');
        if ($leagues_count <= 0) {
            SystemClass::setMessage_user('Не вказано кількість ліг після відбору.');
            $this->setContent('ERROR');
            return;
        }

        if ($group_num < 1 || $group_num > $leagues_count) {
            SystemClass::setMessage_user('Номер ліги поза допустимим діапазоном.');
            $this->setContent('ERROR');
            return;
        }

        $prev_group = (int)db_field('SELECT group_num FROM `bs_league_team_groups` WHERE league_id='.$league_id.' AND team_id='.$team_id.' LIMIT 1', 'group_num');
        if ($prev_group === $group_num) {
            $this->setContent('OK');
            return;
        }

        $sql = 'INSERT INTO `bs_league_team_groups`
                (league_id, team_id, group_num, turnir_id)
                VALUES ('.$league_id.', '.$team_id.', '.$group_num.', '.$turnir_id.')
                ON DUPLICATE KEY UPDATE
                    group_num=VALUES(group_num),
                    turnir_id=VALUES(turnir_id),
                    date_update=NOW()';

        if (db_query($sql)) {
            $user_login = !empty($_SESSION['gt']['user_login']) ? $_SESSION['gt']['user_login'] : 'unknown';
            $user_id = !empty($_SESSION['gt']['user_id']) ? (int)$_SESSION['gt']['user_id'] : 0;
            $log_message = 'Manual team league change: user='.$user_login.' (id='.$user_id.') turnir_id='.$turnir_id.' league_id='.$league_id.' team_id='.$team_id.' group_prev='.$prev_group.' group_new='.$group_num;
            wLog($log_message, 'info', 'logs/team_league');
            $this->setContent('OK');
            return;
        }

        SystemClass::setMessage_user('Не вдалося зберегти зміни.');
        $this->setContent('ERROR');
    }

    function getContent()
    {
        return $this->content;
    }
    function getSubMneu()
    {
        return $this->subMenu;
    }
    function getJavaScript()
    {
        return $this->Java_script;
    }
}

?>
