<?php

namespace Whatarmy_Watchtower;

class Watchtower_API_Endpoint
{

    public function __construct()
    {
        add_filter('query_vars', array($this, 'add_query_vars'), 0);
        add_action('parse_request', array($this, 'sniff_requests'), 0);
        add_action('init', array($this, 'add_endpoint'), 0);
    }


    public function add_query_vars($vars)
    {
        $vars[] = '__watchtower-api';
        $vars[] = 'query';
        $vars[] = 'access_token';
        $vars[] = 'backup_name';

        return $vars;
    }

    // Add API Endpoint
    public function add_endpoint()
    {
        add_rewrite_rule('^watchtower-api/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?', 'index.php?__watchtower-api=1&access_token=$matches[1]&query=$matches[2]', 'top');

    }

    /**
     *
     */
    public function sniff_requests()
    {
        global $wp;
        if (isset($wp->query_vars['__watchtower-api'])) {
            $this->handle_request();
            exit;
        }
    }

    /**
     *
     */
    protected function handle_request()
    {
        global $wp;
        $query = $wp->query_vars['query'];
        $access_token = self::haveAccess($wp->query_vars['access_token']);
        $plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . '../watchtower.php');
        $plugin_version = $plugin_data['Version'];
        switch (true) {
            case($query === 'generate_ota_token' && $access_token):
                $this->send_response(array(
                    'status' => '200 OK',
                    'data'   => WPCore_Model::generate_ota_token()
                ));
                break;
            case ($query === 'login' && $wp->query_vars['access_token']):
                $this->send_response(array(
                    'status' => '200 OK',
                    'data'   => WPCore_Model::sign_in($wp->query_vars['access_token'])
                ));
                break;
            case($query === 'backup_clear' && $access_token && $wp->query_vars['backup_name']):
                if (file_exists(WHT_BACKUP_DIR . '/' . $wp->query_vars['backup_name'])) {
                    unlink(WHT_BACKUP_DIR . '/' . $wp->query_vars['backup_name']);
                }
                $this->send_response(array(
                    'status' => '200 OK',
                ));
                break;
            case($query === 'backup_exist' && $access_token && $wp->query_vars['backup_name']):
                $this->send_response(array(
                    'status' => '200 OK',
                    'exist'  => (file_exists(WHT_BACKUP_DIR . '/' . $wp->query_vars['backup_name'])) ? true : false,
                ));
                break;
            case($query === 'download_backup' && $access_token && $wp->query_vars['backup_name']):
                $this->serveBackup($wp->query_vars['backup_name']);
                break;

            case ($query === 'make_backup' && $access_token && $wp->query_vars['backup_name']):
                $bac = makeMysqlBackup($wp->query_vars['backup_name']);
                if ($bac) {
                    $this->serveBackup($bac);
                }
                break;
            case ($query === 'test' && $access_token):
                $this->send_response(array(
                    'status' => '200 OK',
                ));
                break;
            case ($query === 'core' && $access_token):
                $this->send_response(
                    array(
                        'status'         => '200 OK',
                        'client_version' => $plugin_version,
                        'plugins'        => WPCore_Model::getStat(),
                    ));
                break;
            case ($query === 'plugins' && $access_token):
                $this->send_response(array(
                    'status'         => '200 OK',
                    'client_version' => $plugin_version,
                    'plugins'        => Plugin_Model::getStat(),
                ));
                break;
            case ($query === 'user_logs' && $access_token):
                $this->send_response(array(
                    'status'         => '200 OK',
                    'client_version' => $plugin_version,
                    'logs'           => WPCore_Model::userLogs(),
                ));
                break;
            case ($query === 'themes' && $access_token):
                $this->send_response(
                    array(
                        'status'         => '200 OK',
                        'client_version' => $plugin_version,
                        'plugins'        => Theme_Model::getStat(),
                    ));
                break;
            case ($query === 'all' && $access_token):
                $this->send_response(array(
                    'status'         => '200 OK',
                    'client_version' => $plugin_version,
                    'core'           => WPCore_Model::getStat(),
                    'plugins'        => Plugin_Model::getStat(),
                    'themes'         => Theme_Model::getStat(),
                ));
                break;
            default:
                $this->send_response('Error', 'Invalid request or access token');
                break;
        }

    }

    /**
     * @param $token
     *
     * @return bool
     */
    protected static function haveAccess($token)
    {
        if ($token == get_option('watchtower')['access_token']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $response
     */
    protected function send_response($response)
    {
        header('content-type: application/json; charset=utf-8');
        echo json_encode($response) . "\n";
        exit;
    }

    protected function sendHeaders($file, $type, $name = null, $size)
    {
        if (empty($name)) {
            $name = basename($file);
        }
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $name . '";');
        header('Content-Type: ' . $type);
        header('Content-Length: ' . $size);
    }

    /**
     * @param $filename
     */
    protected function serveBackup($filename)
    {
        define('WP_MEMORY_LIMIT', '512M');
        $file = WHT_BACKUP_DIR . '/' . $filename;
        $mime = mime_content_type($file);
        self::sendHeaders($file, $mime, $filename, filesize($file));
        $chunkSize = 1024 * 8;
        $handle = fopen($file, 'rb');
        while (!feof($handle)) {
            $buffer = fread($handle, $chunkSize);
            echo $buffer;
            if (strpos($filename, 'sql.gz') === false) {
                @ob_end_flush();
            }
            flush();
        }
        fclose($handle);
        exit;
    }
}
