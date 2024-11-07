<?php

declare(strict_types=1);
/**
 * Bizum Payment Module
 *
 * @copyright Copyright 2003-2023 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @updated 06/11/2024
 */

/**
 * NOTA SOBRE LA LICENCIA DE USO DEL SOFTWARE
 *
 * El uso de este software está sujeto a las Condiciones de uso de software que
 * se incluyen en el paquete en el documento "Aviso Legal.pdf". También puede
 * obtener una copia en la siguiente url:
 * http://www.redsys.es/wps/portal/redsys/publica/areadeserviciosweb/descargaDeDocumentacionYEjecutables
 *
 * Redsys es titular de todos los derechos de propiedad intelectual e industrial
 * del software.
 *
 * Quedan expresamente prohibidas la reproducción, la distribución y la
 * comunicación pública, incluida su modalidad de puesta a disposición con fines
 * distintos a los descritos en las Condiciones de uso.
 *
 * Redsys se reserva la posibilidad de ejercer las acciones legales que le
 * correspondan para hacer valer sus derechos frente a cualquier infracción de
 * los derechos de propiedad intelectual y/o industrial.
 *
 * Redsys Servicios de Procesamiento, S.L., CIF B85955367
 */

if (!function_exists('escribirLog')) {
    require_once('redsys/apiRedsys/redsysLibrary.php');
}
if (!class_exists('RedsysAPI')) {
    require_once('redsys/apiRedsys/apiRedsysFinal.php');
}

function tep_db_query_biz($query): queryFactoryResult
{
    global $db;
    return ($db->Execute($query));
}

function tep_db_num_rows_biz($query)
{
    return ($query->RecordCount());
}

/**
 * extending the base allows use of notify-observers
 */
class bizum extends base
{
    /**
     * $_check is used to check the configuration key set up
     * @var int
     */
    protected int $_check;
    /**
     * $code determines the internal 'code' name used to designate "this" payment module
     * @var string
     */
    public string $code;
    /**
     * $description is a soft name for this payment method
     * @var string
     */
    public string $description;
    /**
     * $enabled determines whether this module shows or not... during checkout.
     * @var bool
     */
    public bool $enabled;
    /**
     * $order_status is the order status to set after processing the payment
     * @var int
     */
    public $order_status;
    /**
     * $title is the displayed name for this order total method
     * @var string
     */
    public string $title;
    /**
     * $sort_order is the order priority of this payment module when displayed
     * @var int
     */
    public $sort_order;

    public string $form_action_url = '';
    /**
     * status of log write (currently si/no) TODO
     * @var string
     */
    public string $logActivo;

    public string $logFile = '';
    public bool $mantener_pedido_ante_error_pago;

// class constructor
    function __construct()
    {
        global $order;

        $this->code = 'bizum';
        $this->title = MODULE_PAYMENT_BIZUM_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_BIZUM_TEXT_DESCRIPTION;
        $this->enabled = defined('MODULE_PAYMENT_BIZUM_STATUS') && MODULE_PAYMENT_BIZUM_STATUS == 'True';
        $this->sort_order = defined('MODULE_PAYMENT_BIZUM_SORT_ORDER') ? MODULE_PAYMENT_BIZUM_SORT_ORDER : null;
        if (null === $this->sort_order) {
            return false;
        }

            if (MODULE_PAYMENT_BIZUM_STATUS === 'True' &&
                (
                    MODULE_PAYMENT_BIZUM_NAMECOM === '' ||
                    MODULE_PAYMENT_BIZUM_ID_COM === '' ||
                    strlen(MODULE_PAYMENT_BIZUM_ID_CLAVE256) !== 32
                )
            ) {
                //auto-disable module in storefront
                $this->enabled = false;

                //flag to admin that module is missing data/disabled
                if (IS_ADMIN_FLAG === true) {
                    $this->title .= ' <span class="alert">' . MODULE_PAYMENT_BIZUM_ERROR_NOT_CONFIGURED . '</span>';
                }
            }

        if (defined('MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID;
        }

        $this->mantener_pedido_ante_error_pago = defined('MODULE_PAYMENT_BIZUM_ERROR_PAGO') && (MODULE_PAYMENT_BIZUM_ERROR_PAGO === 'si');

        $this->logActivo = defined('MODULE_PAYMENT_BIZUM_LOG') ? MODULE_PAYMENT_BIZUM_LOG : 'no';

        if (defined('MODULE_PAYMENT_BIZUM_URL')) {
//all except "SIS" are development servers
            switch (MODULE_PAYMENT_BIZUM_URL) {
                case ('SIS-D'):
                    $this->form_action_url = 'http://sis-d.redsys.es/sis/realizarPago/utf-8';
                    break;
                case ('SIS-I'):
                    $this->form_action_url = 'https://sis-i.redsys.es:25443/sis/realizarPago/utf-8';
                    break;
                case ('SIS-T'):
                    $this->form_action_url = 'https://sis-t.redsys.es:25443/sis/realizarPago/utf-8';
                    break;
                case ('SIS'):
                    $this->form_action_url = 'https://sis.redsys.es/sis/realizarPago/utf-8';
                    break;
            }
        } else {
            //no URL defined: disable module
            $this->enabled = false;
        }
        if (is_object($order)) {
            $this->update_status();
        }
    }

// class methods

    public function update_status(): void
    {
        global $order, $db;

        if ($this->enabled && (int)MODULE_PAYMENT_BIZUM_ZONE > 0 && isset($order->delivery['country']['id'])) {
            $check_flag = false;
            $check = $db->Execute("SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '" . MODULE_PAYMENT_BIZUM_ZONE . "' AND zone_country_id = " . (int)$order->delivery['country']['id'] . " ORDER BY zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] === $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag === false) {
                $this->enabled = false;
            }
        }

        // other status checks?
        if ($this->enabled) {
            // other checks here
        }
    }

    /**
     * page Step 2 checkout_payment: returns inline script for validation
     */
    public function javascript_validation()
    {
        return false;
    }

    /**
     * page Step 2 checkout_payment (payment options page)
     * either text can be added to the 'module' field or additional field arrays can be added
     * see authorizenet.php for a detailed example
     * @return array
     */
    public function selection(): array
    {
        return [
            'id' => $this->code,
            //default, shows name only
            //'module' => $this->code;

            //use image and language text
            'module' => MODULE_PAYMENT_BIZUM_TEXT_DESCRIPTION_STOREFRONT_CHECKOUT_PAYMENT
        ];
    }

    /**
     * page Step 3 checkout_confirmation: runs in header
     */
    public function pre_confirmation_check()
    {
        return false;
    }

    /**
     * page Step 3 checkout_confirmation (when payment option has been selected))
     * array allows texts to be shown under the payment method title
     * e.g.
     * Payment Method (always shown)
     * Bizum (always shown)
     * Bizum fn confirmation: array title
     * Bizum fn confirmation: array fields[title]
     * Bizum fn confirmation: array fields[field]
     */
    public function confirmation()
    {
        // example array
        /*
        return [
            'title' => 'Bizum fn confirmation: array title',
            'fields' => [
                [
                    'title' => 'Bizum fn confirmation: array fields[title]',
                    'field' => 'Bizum fn confirmation: array fields[field]'
                ]
            ]
        ];
        */
        return false;
    }

    /**
     * page Step 3 checkout_confirmation (when payment option has been selected)
     * create parameters to send to gateway etc.
     * @return string
     */
    public function process_button(): string
    {
        global $order;

        //added log
        escribirLog("\n================================================================================\n", $this->logActivo);
        escribirLog("BIZUM START - checkout_confirmation - fn_process_button", $this->logActivo);
        escribirLog('Customer: ID=' . $_SESSION['customer_id'] . ', name=' . $order->customer['firstname'] . ' ' . $order->customer['lastname'] . ', session_id=' . zen_session_id(), $this->logActivo);
//eof

        //DATOS PARA EL TPV

        //Merchant Data
        $ds_merchant_data = zen_session_id();

        //Amount
        /*3.0.1 breaks for values>999.99!
        $total= number_format($order->info['total'], 2);//order total to two decimal places. May be any of the currencies offered in the shop. This info['total'] is always formatted as 1234567.89 but this line will change that to 1,234,567.89 which is a STRING
        $cantidad = round($total*$order->info['currency_value'],2);//order total multiplied by selected currency exchange rate to default currency as defined in the shop admin. "round" works with FLOATS and the previous line created a STRING so the result is 1.
        $cantidad = number_format($cantidad, 2, '.', '');
        $cantidad = preg_replace('/\./', '', $cantidad);
        */
        $cantidad = (int)number_format(round($order->info['total'] * $order->info['currency_value'], 2), 2, '', '');
        escribirLog('$cantidad=' . $cantidad . ' (no commas or decimals)', $this->logActivo);

        //Id_Pedido

        // This number is actually a record of the payment ATTEMPT when customer first clicks on the payment button to go to the TPV.
        // The actual Order ID registered in the shop is only created at the end of the successful transaction.

        //choose what you prefer
        //12 chars max. 1-4:digits, 5-12 : 0-9/a-z/A-Z
        //$numpedido = rand(10,10000);
        //$numpedido = date("ymdHi"). rand(00,99);//alternative
        //echo '$numpedido='.$numpedido.'<br />';

        $numpedido = rand(10, 10000);
        escribirLog('$numpedido=' . $numpedido . ' (payment attempt)', $this->logActivo);

        //Nombre Com.
        $ds_merchant_name = MODULE_PAYMENT_BIZUM_NAMECOM;

        //Tipo MONEDA.
        if (MODULE_PAYMENT_BIZUM_MERCHANT_CURRENCY === 'DOLAR') {
            $moneda = '840';
        } else {
            $moneda = '978'; //EURO POR DEFECTO
        }
        escribirLog('$moneda=' . $moneda . ' (currency)', $this->logActivo);

        //Nombre Terminal.
        $terminal = MODULE_PAYMENT_BIZUM_TERMINAL;

        //Transaction Type
        $trans = '0';

        //Idioma
       /*
        $idioma_tpv = '0';
        if ($language == 'english') {
            $idioma_tpv = '002';
        }
       */

//bof steve to override lack of language handling
// The Redsys code above sets it as 0 "default" unless session language is english 002.But spanish is 001, so wtf?

        // optional suffix to add to the store name on the customers receipt
        $ds_merchant_name_suffix = '';

        //$_SESSION['language'] is the name for the directory of the language files and NOT the name as shown on the storefront
        switch ($_SESSION['language']) {
            case 'spanish':
                $idioma_tpv = '001';
                $ds_merchant_name_suffix = ' (España)';
                break;
            case 'english':
                $idioma_tpv = '002';
                $ds_merchant_name_suffix = ' (Spain)';
                break;
            case 'catalan':
                $idioma_tpv = '003';
                break;
            case 'french':
                $idioma_tpv = '004';
                break;
            case 'german':
                $idioma_tpv = '005';
                break;
            case 'dutch':
                $idioma_tpv = '006';
                break;
            case 'italian':
                $idioma_tpv = '007';
                break;
            case 'swedish':
                $idioma_tpv = '008';
                break;
            case 'portuguese':
                $idioma_tpv = '009';
                break;
            case 'valencian':
                $idioma_tpv = '010';
                break;
            case 'polish':
                $idioma_tpv = '011';
                break;
            case 'galician':
                $idioma_tpv = '012';
                break;
            case 'basque':
                $idioma_tpv = '013';
                break;
            case 'bulgarian':
                $idioma_tpv = '100';
                break;
            case 'chinese':
                $idioma_tpv = '156';
                break;
            case 'croatian':
                $idioma_tpv = '191';
                break;
            case 'danish':
                $idioma_tpv = '208';
                break;
            case 'estonian':
                $idioma_tpv = '233';
                break;
            case 'finnish':
                $idioma_tpv = '246';
                break;
            case 'greek':
                $idioma_tpv = '300';
                break;
            case 'hungarian':
                $idioma_tpv = '348';
                break;
            case 'japonese':
                $idioma_tpv = '392';
                break;
            default:
                $idioma_tpv = '001';
                $ds_merchant_name_suffix = ' (España)';
        }
        escribirLog('$idioma_tpv=' . $idioma_tpv . ' ($_SESSION[\'language\']="' . $_SESSION['language'] . '")', $this->logActivo);
//eof language

        //URL OK Y KO
        $ds_merchant_urlok = zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');

        // logoff on a failure...how to lose a customer!
        //$ds_merchant_urlko = zen_href_link(FILENAME_LOGOFF, 'error_message=ERROR', 'NONSSL', true, false);

        // for better error handling...but zen_href_link encodes & to &amp; which we don't want, so remove it on next line.
        $ds_merchant_urlko = zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=bizum', 'SSL');
        $ds_merchant_urlko = str_replace('&amp;', '&', $ds_merchant_urlko);

        //URL Respuesta ONLINE
        $home = explode('/', $_SERVER['REQUEST_URI']);
        $urltienda = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $home[1] . '/bizum_process.php';
        escribirLog('$urltienda=' . $urltienda . ' (processing)', $this->logActivo);

        //Firma
        $clave256 = MODULE_PAYMENT_BIZUM_ID_CLAVE256;
        $codigo = MODULE_PAYMENT_BIZUM_ID_COM;

        $ds_product_description = '';
        for ($i = 0; $i < sizeof($order->products); $i++) {
            $ds_product_description = $order->products[$i]['qty'] . 'x' . $order->products[$i]['name'] . '/';
        }

        $ds_merchant_titular = $order->customer['firstname'] . ' ' . $order->customer['lastname'];

        $miObj = new RedsysAPI();
        $miObj->setParameter('DS_MERCHANT_AMOUNT', $cantidad);
        $miObj->setParameter('DS_MERCHANT_ORDER', strval($numpedido));
        $miObj->setParameter('DS_MERCHANT_MERCHANTCODE', $codigo);
        $miObj->setParameter('DS_MERCHANT_CURRENCY', $moneda);
        $miObj->setParameter('DS_MERCHANT_TRANSACTIONTYPE', $trans);
        $miObj->setParameter('DS_MERCHANT_TERMINAL', $terminal);
        $miObj->setParameter('DS_MERCHANT_MERCHANTURL', $urltienda);
        $miObj->setParameter('DS_MERCHANT_URLOK', $ds_merchant_urlok);
        $miObj->setParameter('DS_MERCHANT_URLKO', $ds_merchant_urlko);
        $miObj->setParameter('Ds_Merchant_ConsumerLanguage', $idioma_tpv);
        $miObj->setParameter('Ds_Merchant_ProductDescription', $ds_product_description);
        $miObj->setParameter('Ds_Merchant_Titular', $ds_merchant_titular);
        $miObj->setParameter('Ds_Merchant_MerchantData', $ds_merchant_data);
        $miObj->setParameter('Ds_Merchant_MerchantName', $ds_merchant_name);
        $miObj->setParameter('Ds_Merchant_PayMethods', 'z');
        $miObj->setParameter('Ds_Merchant_Module', 'zencart_bizum_3.0.2');

        //Datos de configuración
        $version = getVersionClave();

        //Clave del comercio que se extrae de la configuración del comercio
        // Se generan los parámetros de la petición
        $request = '';
        $paramsBase64 = $miObj->createMerchantParameters();
        $signatureMac = $miObj->createMerchantSignature($clave256);

        $process_button_string =
            zen_draw_hidden_field('Ds_SignatureVersion', $version) .
            zen_draw_hidden_field('Ds_MerchantParameters', $paramsBase64) .
            zen_draw_hidden_field('Ds_Signature', $signatureMac);
        return $process_button_string;
    }

    /** called by checkout_process to handle gateway response
     * @return void
     */
    function before_process(): void
    {
        $idLog = generateIdLog();

        $valido = false;
        if (!empty($_POST)) {//URL DE RESP. ONLINE

            $clave256 = MODULE_PAYMENT_BIZUM_ID_CLAVE256;

            /** Recoger datos de respuesta **/
            $version = $_POST['Ds_SignatureVersion'];
            $datos = $_POST['Ds_MerchantParameters'];
            $firma_remota = $_POST['Ds_Signature'];

            // Se crea Objeto
            $miObj = new RedsysAPI();

            /** Se decodifican los datos enviados y se carga el array de datos **/
            $decodec = $miObj->decodeMerchantParameters($datos);

            /** Se calcula la firma **/
            $firma_local = $miObj->createMerchantSignatureNotif($clave256, $datos);

            /** Extraer datos de la notificación **/
            $total = $miObj->getParameter('Ds_Amount');
            $pedido = $miObj->getParameter('Ds_Order');
            $codigo = $miObj->getParameter('Ds_MerchantCode');
            $moneda = $miObj->getParameter('Ds_Currency');
            $respuesta = $miObj->getParameter('Ds_Response');
            $id_trans = $miObj->getParameter('Ds_AuthorisationCode');

            //Nuevas variables
            $codigoOrig = MODULE_PAYMENT_BIZUM_ID_COM;

            if (checkRespuesta($respuesta)
                && checkMoneda($moneda)
                && checkFuc($codigo)
                && checkPedidoNum($pedido)
                && checkImporte($total)
                && $codigo == $codigoOrig
            ) {
                escribirLog($idLog . ' -- El pedido con ID ' . $pedido . ' es válido y se ha registrado correctamente.', $this->logActivo);
                $valido = true;
            } else {
                escribirLog($idLog . ' -- Parámetros incorrectos.', $this->logActivo);
                if (!checkImporte($total)) {
                    escribirLog($idLog . ' -- Formato de importe incorrecto.', $this->logActivo);
                }
                if (!checkPedidoNum($pedido)) {
                    escribirLog($idLog . ' -- Formato de nº de pedido incorrecto.', $this->logActivo);
                }
                if (!checkFuc($codigo)) {
                    escribirLog($idLog . ' -- Formato de FUC incorrecto.', $this->logActivo);
                }
                if (!checkMoneda($moneda)) {
                    escribirLog($idLog . ' -- Formato de moneda incorrecto.', $this->logActivo);
                }
                if (!checkRespuesta($respuesta)) {
                    escribirLog($idLog . ' -- Formato de respuesta incorrecto.', $this->logActivo);
                }
                if (!checkFirma($firma_remota)) {
                    escribirLog($idLog . ' -- Formato de firma incorrecto.', $this->logActivo);
                }
                escribirLog($idLog . ' -- El pedido con ID ' . $pedido . ' NO es válido.', $this->logActivo);
                $valido = false;
            }

            if ($firma_local != $firma_remota || false === $valido) {
                //El proceso no puede ser completado, error de autenticación
                escribirLog($idLog . ' -- La firma no es correcta.', $this->logActivo);
                $_SESSION['cart']->reset(true);
                zen_redirect(zen_href_link(FILENAME_LOGOFF, 'error_message=ERROR DE FIRMA', 'NONSSL', true, false));
            }

            $iresponse = (int)$respuesta;

            if (($iresponse >= 0) && ($iresponse <= 100)) {
                //after_Process();
            } else {
                if (!$this->mantener_pedido_ante_error_pago) {
                    $_SESSION['cart']->reset(true);
                    escribirLog($idLog . ' -- Error de respuesta. Vaciando carrito.', $this->logActivo);
                    zen_redirect(zen_href_link(FILENAME_LOGOFF, 'error_message=ERROR DE RESPUESTA', 'NONSSL', true, false));
                } else {
                    escribirLog($idLog . ' -- Error de respuesta. Manteniendo carrito.', $this->logActivo);
                    zen_redirect(zen_href_link(FILENAME_CHECKOUT, 'error_message=ERROR DE RESPUESTA', 'NONSSL', true, false));
                }
            }
        } else {
            //Transacción denegada
            escribirLog($idLog . ' -- Error. Hacking atempt!', $this->logActivo);
            die ('Hacking atempt!');
            exit;
        }
    }

    /**
     * checkout_process
     */
    public function after_process()
    {
        global $db, $insert_id;

        //Actualizamos el Status del pedido
        $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = " . MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID . ",payment_method = 'Pago mediante Bizum',payment_module_code = 'bizum' WHERE orders_id = '" . $insert_id . "'");
        $db->Execute("UPDATE " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_status_id = " . MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID . " WHERE orders_id = '" . $insert_id . "'");
        //Borrar carrito
        $_SESSION['cart']->reset(true);
        //zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'NONSSL'));
        return false;
    }

    /** used in checkout_Payment header to process any error message/try again
     *
     */
    public function get_error(): false
    {
        return false;
    }

    /** called by module
     * @return int
     */
    public function check(): int
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BIZUM_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * @return array
     */
    public function getCurrenciesInstalled(): array
    {
        global $db;
        $currencies = [];
        $currency_query_raw = "select currencies_id, title, code, symbol_left, symbol_right, decimal_point, thousands_point, decimal_places, last_updated, value from " . TABLE_CURRENCIES . " order by title";
        $currency = $db->Execute($currency_query_raw);

        while (!$currency->EOF) {
            $cInfo = new objectInfo($currency->fields);
            array_push($currencies, $cInfo);
            $currency->MoveNext();
        }
        return $currencies;
    }

    /**
     * @return string only if failed, returning anything other than "failed" is ignored
     */
    public function install(): string
    {
        global $db, $messageStack;
        if (defined('MODULE_PAYMENT_BIZUM_STATUS')) {
            $messageStack->add_session('BIZUM module already installed.', 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=bizum', 'NONSSL'));
            return 'failed';
        }
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activar modulo Bizum', 'MODULE_PAYMENT_BIZUM_STATUS', 'True', '¿Quiere aceptar pagos usando Bizum?', '6', '3', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_BIZUM_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Order Status', 'MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID', '0', 'Selecciona el estado final del pedido', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Orden de aparición', 'MODULE_PAYMENT_BIZUM_SORT_ORDER', '10', 'Orden de aparición. Un número menor es mostrado antes que los mayores.', '6', '0', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Nombre Comercio Bizum', 'MODULE_PAYMENT_BIZUM_NAMECOM', '', 'Nombre de comercio', '6', '4', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('FUC Comercio Bizum', 'MODULE_PAYMENT_BIZUM_ID_COM', '', 'Código de comercio proporcionado por la entidad bancaria', '6', '4', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Clave de Encriptación (SHA-256)', 'MODULE_PAYMENT_BIZUM_ID_CLAVE256', '', 'Clave de encriptación SHA-256 proporcionada por la entidad bancaria', '6', '4', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Terminal', 'MODULE_PAYMENT_BIZUM_TERMINAL', '1', 'Terminal de pago en Redsys', '6', '4', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Tipo de moneda', 'MODULE_PAYMENT_BIZUM_MERCHANT_CURRENCY', 'EURO', 'Código correspondiente a la moneda EURO', '6', '4','zen_cfg_select_option(array(\'EURO\', \'DOLAR\'), ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Entorno de Bizum', 'MODULE_PAYMENT_BIZUM_URL', 'SIS-D', 'URL de la pasarela de pago', '6', '4','zen_cfg_select_option(array(\'SIS-D\', \'SIS-I\', \'SIS-T\', \'SIS\'), ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,date_added) VALUES ('Error pago', 'MODULE_PAYMENT_BIZUM_ERROR_PAGO', 'si', '¿Mantener carrito si se produce un error en el pago?', '6', '4','zen_cfg_select_option(array(\'si\', \'no\'), ',  now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,date_added) VALUES ('Log activo', 'MODULE_PAYMENT_BIZUM_LOG', 'no', '¿Crear trazas de log?', '6', '4','zen_cfg_select_option(array(\'si\', \'no\'), ',  now())"
        );

        //allow an observer to update the constants with site-specific values
        $this->notify('NOTIFY_PAYMENT_BIZUM_POST_INSTALL', $this->keys());

        return '';
    }

    /** delete the configuration keys installed by module
     * @return void
     */
    public function remove(): void
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    /** list of configuration keys installed by module
     * @return array
     */
    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_BIZUM_STATUS',
            'MODULE_PAYMENT_BIZUM_ZONE',
            'MODULE_PAYMENT_BIZUM_ORDER_STATUS_ID',
            'MODULE_PAYMENT_BIZUM_SORT_ORDER',
            'MODULE_PAYMENT_BIZUM_NAMECOM',
            'MODULE_PAYMENT_BIZUM_ID_COM',
            'MODULE_PAYMENT_BIZUM_ID_CLAVE256',
            'MODULE_PAYMENT_BIZUM_TERMINAL',
            'MODULE_PAYMENT_BIZUM_MERCHANT_CURRENCY',
            'MODULE_PAYMENT_BIZUM_URL',
            'MODULE_PAYMENT_BIZUM_ERROR_PAGO',
            'MODULE_PAYMENT_BIZUM_LOG'
        ];
    }
}
