<!DOCTYPE html>
<!-- wos-lite api V1.1: bu yazılım Dr. Zafer Akçalı tarafından oluşturulmuştur 
programmed by Zafer Akçalı, MD -->
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>WOS numarasından makaleyi bul</title>
</head>

<body>
<?php
// wos-lite api
// By Zafer Akçalı, MD
// Zafer Akçalı tarafından programlanmıştır
$wosid=$doi=$ArticleTitle=$dergi=$ISOAbbreviation=$ISSN=$eISSN=$Year=$Volume=$Issue=$StartPage=$EndPage=$yazarlar=$PublicationType=$AbstractText=$PublicationAccess="";
$yazarS=0;
if (isset($_POST['wosid'])) {
$gelenWos=trim($_POST["wosid"]);

if( substr($gelenWos,0,4) !== "WOS:")
	$gelenWos="WOS:".$gelenWos; // sadece rakamları girdiyse başına WOS: ekle 
	
if($gelenWos!=""){

$wosNumberQuery='UT='.$gelenWos;

// to get different session id's from Web of Science for different users, and use them for one hour
if (isset($_COOKIE['wSID'])) $wSID = $_COOKIE['wSID'];	else {	
	$auth_url  = "http://search.webofknowledge.com/esti/wokmws/ws/WOKMWSAuthenticate?wsdl";
	try {
		$auth_client = @new SoapClient($auth_url);		
		$auth_response = $auth_client->authenticate();
		} catch (Exception $e) {
				echo $e->getMessage(), "<br>"; 
				if (strpos ($e->getMessage(), 'No matches returned for IP') !== false)
				exit("üzgünüz, web of scienca'a bağlanamıyor"); 
		}	
	$wSID = $auth_response->return;
	setcookie ('wSID',$wSID,time()+1*60*60) ; // delete cookie after one hour, to be able to get a new one
	} 
// echo $wSID, " sessionID'si kullanılıyor. ";

// api için hazırlık yap
$search_url = "http://search.webofknowledge.com/esti/wokmws/ws/WokSearchLite?wsdl";
$search_client = @new SoapClient($search_url);
$search_client->__setCookie('SID',$wSID);
$search_array = array(
  'queryParameters' => array(
    'databaseId' => 'WOS',
    'userQuery' => $wosNumberQuery,
    'editions' => array(
    array('collection' => 'WOS', 'edition' => 'SCI'),
    array('collection' => 'WOS', 'edition' => 'SSCI'),
	array('collection' => 'WOS', 'edition' => 'AHCI'),
	array('collection' => 'WOS', 'edition' => 'ESCI'),
	array('collection' => 'WOS', 'edition' => 'ISTP'),
	array('collection' => 'WOS', 'edition' => 'ISSHP'),
	array('collection' => 'WOS', 'edition' => 'BSCI'),
	array('collection' => 'WOS', 'edition' => 'BHCI'),
    ),
	'queryLanguage' => 'en'
  ),
  'retrieveParameters' => array(
    'count' => '1',
    'firstRecord' => '1',
  )
);

try{
  $search_response = $search_client->search($search_array);
} catch (Exception $e) {  
//    echo $e->getMessage(),"<br>";
//	exit("Bir hata oldu, sorgulama yapılamıyor"); 
}
// ilk sorguyla kaç yanıt olduğu ve queryId geliyor
$resp =(json_decode(json_encode($search_response->return), true));
$n = (int)$resp['recordsFound']; //total number of records to be returned

if ($n !=0) { //kayıt var ise
// PERFORM a Retrieve operation, by using queryId
$queryId=$resp['queryId'];
$retCount= 1; // total number of records to retrieved
$recNumber=0; // record number to be printed

$retrieve_array = array(
	'queryId' => $queryId,
	'retrieveParameters' => array(
    'count' => 1,
    'firstRecord' => 1
  )
);

try{
  $retrieve_response = $search_client->retrieve($retrieve_array);
} catch (Exception $e) {  
    echo $e->getMessage(),"<br>";
	exit("Bir hata oldu, sorgulama yapılamıyor"); 
}
$resp =(json_decode(json_encode($retrieve_response->return), true));
// print_r ($resp) ;  // for debugging response text
$onerecord = $resp['records'];
// Makalenin başlığı
$ArticleTitle=$onerecord['title']['value'];
// Özet gelmiyor
// $AbstractText=$wosBilgi['abstract'];

if (is_array ($onerecord['doctype']['value']))  {
	$PublicationType = $onerecord['doctype']['value'][0]; // Doctypes are grouped as arrays
	$PublicationAccess = $onerecord['doctype']['value'][1];
		}
	else $PublicationType = $onerecord['doctype']['value']; // Doctypes are not grouped as arrays	
		
for ($i=0; $i < count ($onerecord['other']); $i++) { 
	if ($onerecord['other'][$i]['label'] == "Identifier.Doi" or // doi
		$onerecord['other'][$i]['label'] == "Identifier.Xref_Doi" 	// doi			
		)  
		$doi=$onerecord['other'][$i]['value'];
	if ($onerecord['other'][$i]['label'] == "Identifier.Issn") // issn
			$ISSN=$onerecord['other'][$i]['value'];
	if ($onerecord['other'][$i]['label'] == "Identifier.Eissn") // eissn
			$eISSN=$onerecord['other'][$i]['value'];
	if ($onerecord['other'][$i]['label'] == "Identifier.article_no" ) // makale no
		$StartPage=$onerecord['other'][$i]['value'];
	}
			
// WOS numarası
$wosid=$onerecord['uid'];

for ($i=0; $i < count ($onerecord['source']); $i++) { 
	if ($onerecord['source'][$i]['label'] == "SourceTitle") // Dergi ismi
		$dergi=$onerecord['source'][$i]['value'];
	if ($onerecord['source'][$i]['label'] == "Published.BiblioYear") // Derginin basıldığı / yayımlandığı yıl
			$Year= $onerecord['source'][$i]['value'];
	if ($onerecord['source'][$i]['label'] == "Volume") 		// Cilt
		$Volume=$onerecord['source'][$i]['value'];
	if ($onerecord['source'][$i]['label'] == "Issue") 		// Sayı
		$Issue=$onerecord['source'][$i]['value'];
	if ($onerecord['source'][$i]['label'] == "Pages")	{	// Sayfalar 
	if ($onerecord['source'][$i]['value']) {
		$sayfalar=explode ("-", $onerecord['source'][$i]['value']);
		$StartPage= $sayfalar[0];
		$EndPage=$sayfalar[1];
				}
			}
}
		
// Dergi kısa ismi gelmiyor, publons api'yle dene
// $ISOAbbreviation=$wosBilgi['journal']['abbreviatedTitle'];

// yazar sayısı
$yazarS=0;
// yazarlar
$yazarlar="";

if (array_key_exists(0, $onerecord['authors']) == TRUE )  	 	
		$authorArray = $onerecord['authors'][0]; // Yazarlar ve grup yazarlar var 
	else $authorArray = $onerecord['authors']; // Sadece yazarlar var, grup yazarlar yok

if (count ($authorArray['value']) == 1 ) { // tek yazar var
	$yazarS=1;
	$soyadAd=explode (", ", $onerecord['authors']['value']);
	$soyisim=$soyadAd[0];
	$isim=$soyadAd[1];
	$yazarlar=$yazarlar.$isim." ".$soyisim;
		}
	else	{ // birden fazla yazar var
		for ($i=0; $i < count ($authorArray['value']); $i++) { 
			$soyadAd=explode (", ", ($authorArray['value'][$i]));
			$soyisim=$soyadAd[0];
			$isim=$soyadAd[1];
			$yazarlar=$yazarlar.$isim." ".$soyisim.", ";
			$yazarS=$yazarS+1;
			} 
		$yazarlar=substr ($yazarlar,0,-2); // son yazardan sonraki virgül ve boşluğu sil
			}  
		} // {"detail":"Not found."} hatası gelmedi
	} 
// wos - lite api ile gelmeyen bilgileri almayı dene
$publonsText="https://publons.com/wos-op/api/publication/";
$url = $publonsText.$gelenWos;
$publonsHtml=@file_get_contents($url);
if ($publonsHtml) {
$publonsBilgi=(json_decode($publonsHtml, true));
// Özet
$AbstractText=$publonsBilgi['abstract'];
// Dergi kısa ismi
$ISOAbbreviation=$publonsBilgi['journal']['abbreviatedTitle'];
	}
}
?>
<a href="WOSid nerede.png" target="_blank"> WOS numarasına nereden bakılır? </a>
<form method="post" action="">
Web of Science (WOS) makale numarasını giriniz<br/>
<input type="text" name="wosid" id="wosid" value="<?php echo $wosid;?>" >
<input type="submit" value="WOS yayın bilgilerini PHP ile getir">
</form>
<button id="wosGoster" onclick="wosGoster()">WOS yayınını göster</button>
<button id="wosAtifGoster" onclick="wosAtifGoster()">WOS yayınının atıflarını göster</button>
<button id="doiGit" onclick="doiGit()">doi ile makaleyi göster</button>
<br/>
WOS: <input type="text" name="wosid" size="19" id="wosid" value="<?php echo $wosid;?>" >  
doi: <input type="text" name="doi" size="55"  id="doi" value="<?php echo $doi;?>"> <br/>
Makalenin başlığı: <input type="text" name="ArticleTitle" size="85"  id="ArticleTitle" value="<?php echo $ArticleTitle;?>"> <br/>
Dergi ismi: <input type="text" name="Title" size="50"  id="Title" value="<?php echo $dergi;?>"> 
Kısa ismi: <input type="text" name="ISOAbbreviation" size="26"  id="ISOAbbreviation" value="<?php echo $ISOAbbreviation;?>"> <br/>
ISSN: <input type="text" name="ISSN" size="8"  id="ISSN" value="<?php echo $ISSN;?>">
eISSN: <input type="text" name="eISSN" size="8"  id="eISSN" value="<?php echo $eISSN;?>"> <br/>
Yıl: <input type="text" name="Year" size="4"  id="Year" value="<?php echo $Year;?>">
Cilt: <input type="text" name="Volume" size="2"  id="Volume" value="<?php echo $Volume;?>">
Sayı: <input type="text" name="Issue" size="2"  id="Issue" value="<?php echo $Issue;?>">
Sayfa/numara: <input type="text" name="StartPage" size="2"  id="StartPage" value="<?php echo $StartPage;?>">
- <input type="text" name="EndPage" size="2"  id="EndPage" value="<?php echo $EndPage;?>">
Yazar sayısı: <input type="text" name="yazarS" size="2"  id="yazarS" value="<?php echo $yazarS;?>"><br/>
Yazarlar: <input type="text" name="yazarlar" size="95"  id="yazarlar" value="<?php echo $yazarlar;?>"><br/>
Yayın türü: <input type="text" name="PublicationType" size="20"  id="PublicationType" value="<?php echo $PublicationType;?>">
Yayın erişimi: <input type="text" name="PublicationAccess" size="20"  id="PublicationAccess" value="<?php echo $PublicationAccess;?>">
<br/>
Özet <br/>
<textarea rows = "20" cols = "90" name = "ozet" id="ozetAlan"><?php echo $AbstractText;?></textarea>  <br/>
Beyan edilecek bilgiler (seçiniz). Makaleyi web of science sitesinde görüp, sağ alt köşeye bakmalısınız.<br/> Bir yayın hem SCIE, hem de SSCI kapsamında; hem SSCI, hem de AHCI kapsamında olabilir. <br/>
Faaliyet-1: SCIE 
<select name="SCIE" size="1" >
<option value = "Hayır"></option>
<option value = "Evet">*</option>
</select> <br/>
Faaliyet-2: SSCI<select name="SSSCI" size="1" >
<option value = "Hayır"></option>
<option value = "Evet">*</option>
</select> 
AHCI<select name="AHCI" size="1" >
<option value = "Hayır"></option>
<option value = "Evet">*</option>
</select> <br/>
Faaliyet-3 ESCI <select name="ESCI" size="1" >
<option value = "Hayır"></option>
<option value = "Evet">*</option>
</select> <br/>
Faaliyet-17: CPCI <select name="CPCI" size="1" >
<option value = "Hayır"></option>
<option value = "Evet">*</option>
</select> <br/>
Faaliyet 8-10 : BKCI <select name="BKCI" size="1" >
<option value = "Hayır"></option>
<option value = "Evet">*</option>
</select> (Eğer WOS'da kayıtlı ise belge koymaya gerek kalmaz)<br/>
En iyi Quartile değeri:<select name="Quartile" size="1" >
<option value = "Yok"></option>
<option value = "Q1">Q1</option>
<option value = "Q2">Q2</option>
<option value = "Q3">Q3</option>
<option value = "Q3">Q4</option>
</select> Quartile değeri için, Journal Citation Reports sitesine bakmalısınız<br/>
<script>
function wosGoster() {
var	w=document.getElementById('wosid').value.replace("WOS:","").replace(" ","");
	urlText = "https://www.webofscience.com/wos/woscc/full-record/"+"WOS:"+w;
	window.open(urlText,"_blank");
}
function wosAtifGoster() {
var	w=document.getElementById('wosid').value.replace("WOS:","").replace(" ","");
	urlText = "https://www.webofscience.com/wos/woscc/citing-summary/"+"WOS:"+w;
	window.open(urlText,"_blank");
}
function doiGit() {
var	w=document.getElementById('doi').value;
	urlText = "https://doi.org/"+w;
	window.open(urlText,"_blank");
}
</script>
</body>
</html>