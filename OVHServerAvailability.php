<?php

class OVHServerAvailability {
    private $types = [
        'KS1' => '160sk1',
        'KS2' => '160sk21'
    ];
    private $zones = [
        'gra'    => 'FRANCE NORD (Graveline)',
        'rbx-hz' => 'FRANCE NORD (Roubaix innovation)',
        'sbg'    => 'FRANCE EST (Strasbourg)',
        'rbx'    => 'FRANCE NORD (Roubaix)',
        'bhs'    => 'AMERIQUE DU NORD (Beauharnois)',
        'unknown'=> 'INCONNU'
    ];
    private $urls = [
        'session'       => 'https://ws.ovh.com/sessionHandler/r4/ws.dispatcher/getAnonymousSession?callback=&params={{params}}',
        'availability'  => 'https://ws.ovh.com/order/dedicated/servers/ws.dispatcher/getPossibleOptionsAndAvailability?callback=&params={{params}}',
        'buy'           => 'https://www.kimsufi.com/fr/commande/kimsufi.xml?reference={{reference}}&quantity={{quantity}}'
    ];
    private $session = false;


    public function __construct()
    {
        $this->session = $this->getSession();
    }

    public function getZone($zone)
    {
        return (!isset($this->zones[$zone])) ? $this->zones['unknown'] : $this->zones[$zone];
    }

    public function getBuyLink($reference, $quantity = 1)
    {
        $link = str_replace('{{reference}}', $reference, $this->urls['buy']);
        $link = str_replace('{{quantity}}', $quantity, $link);

        return $link;
    }

    public function getSession()
    {
        $params = ["language" => "fr"];
        $result = $this->requestJSON($this->urls['session'], $params);

        return $result['answer']['session']['id'];
    }

    public function getAvailability($type, $quantity = 1)
    {
        $params = [
            "sessionId" => $this->session,
            "billingCountry" => "KSFR",
            "dedicatedServer" => $this->types[$type],
            "installFeeMode" => "directly",
            "quantity" => $quantity,
            "duration" => "1m"
        ];

        $result = $this->requestJSON($this->urls['availability'], $params);
        $result = $this->formatAvailability($result);

        return $result;
    }

    public function formatAvailability($datas)
    {
        $datas = $datas['answer'][0];

        $formatedDatas = [];
        $formatedDatas['name'] = $datas['mainReferences'][0]['designation'];
        $formatedDatas['reference'] = $datas['reference'];

        $formatedDatas['price'] = [];
        $formatedDatas['price']['ht'] = $datas['totalPrice'];
        $formatedDatas['price']['ttc'] = $datas['totalPriceWithVat'];
        $formatedDatas['price']['unit'] = $datas['mainReferences'][0]['price'];
        $formatedDatas['price']['install'] = $datas['mainReferences'][1]['price'];

        $formatedDatas['quantity'] = $datas['quantity'];
        $formatedDatas['duration'] = $datas['duration'];
        $formatedDatas['available'] = 0;
        $formatedDatas['zones'] = [];

        foreach ($datas['zones'] as $zone) {
            $availability = $zone['availability'];
            $availability = ($availability < 0) ? 0 : $availability;

            $formatedDatas['zones'][$zone['zone']] = $availability;
            $formatedDatas['available'] += $availability;
        }

        return $formatedDatas;
    }

    public function request($url, $params = [])
    {
        $url = str_replace('{{params}}', json_encode($params), $url);
        return @file_get_contents($url);
    }

    public function requestJSON($url, $params = [])
    {
        $return = $this->request($url, $params);
        return json_decode($return, true);
    }
}
