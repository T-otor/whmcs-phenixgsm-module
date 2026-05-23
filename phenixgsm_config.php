<?php
if (!defined("WHMCS")) die("Direct access forbidden");

use Illuminate\Database\Capsule\Manager as Capsule;

define("PHENIXGSM_API_BASE_URL", "https://api.phenix-partner.fr");
define("PHENIXGSM_TOKEN_CACHE_TTL", 3600);
define("PHENIXGSM_CACHE_TABLE", "mod_phenixgsm_tokens");
define("PHENIXGSM_DEBUG", false);
define("PHENIXGSM_LOG_FILE", __DIR__ . "/logs/phenixgsm_" . date("Y-m-d") . ".log");

define("PHENIXGSM_OPERATORS", serialize(["ORANGE"=>"Orange","SFR"=>"SFR","BTBD"=>"Bouygues","PHENIX"=>"Phenix"]));
define("PHENIXGSM_SIM_TYPES", serialize(["SIM"=>"SIM Standard","ESIM"=>"eSIM","SIM15D"=>"SIM 15D","SIM15D_IPFIXE"=>"SIM 15D IP Fixe","ESIM15D"=>"eSIM 15D","ESIM15D_DataOnly"=>"eSIM 15D Data Only"]));

function phenixgsm_initCacheTable() {
    if (!Capsule::schema()->hasTable(PHENIXGSM_CACHE_TABLE)) {
        Capsule::schema()->create(PHENIXGSM_CACHE_TABLE, function($table) {
            $table->increments("id");
            $table->string("partenaireId", 100);
            $table->text("access_token");
            $table->string("working_token", 100)->nullable();
            $table->integer("expires_in");
            $table->string("token_type", 50);
            $table->timestamp("created_at")->useCurrent();
            $table->timestamp("expires_at")->nullable();
        });
    }
}
phenixgsm_initCacheTable();

function phenixgsm_formatMsisdn($phone) {
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (substr($phone, 0, 1) === "0") $phone = "33" . substr($phone, 1);
    if (substr($phone, 0, 3) === "+33") $phone = substr($phone, 1);
    return $phone;
}
