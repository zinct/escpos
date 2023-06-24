<?php
  require __DIR__ . '/vendor/autoload.php';

  use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
  use Mike42\Escpos\Printer;
  use GuzzleHttp\Client;
  use GuzzleHttp\Exception\RequestException;

  // Get Parameter
  $params = explode("/", $_SERVER['REQUEST_URI']);
  
  $penjualan_id = @$params[2] ? $params[2] : null;
  $dibayar = @$params[3] ? $params[3] : null;

  if(!$penjualan_id) {
    echo 'Penjualan ID Tidak Ada'; die;
  } 

  $client = new Client();
  try {
    $response = $client->request('GET', "http://localhost:8000/api/penjualan/{$penjualan_id}");
    $data = json_decode($response->getBody());
  } catch (RequestException $e) {
    echo 'Data Tidak Ditemukan';
    die;
  }

  try {  

    $connector = new WindowsPrintConnector($printer);
    $printer = new Printer($connector);

    /* Name of shop */
    $printer -> setJustification(Printer::JUSTIFY_CENTER);
    $printer -> text("PRIMKOPPOL SATBRIMOB POLDA JABAR\n");
    $printer -> selectPrintMode();
    $printer -> text("JALAN KOL ACHMAD SYAM 17 A.\n");

    $printer -> text("--------------------------------\n");
    
    /* Title of receipt */
    $printer -> setJustification(Printer::JUSTIFY_LEFT);

    if($data->pelanggan) :
        $printer -> text($data->pelanggan->nama . "\n");
        $printer -> text($data->pelanggan->id_pelanggan . "\n");
        $printer -> text("--------------------------------\n");
    endif;

    $printer -> text("Staff : {$data->user->nama}\n");
    $printer -> text(new Item(date('d-m-Y'), date('H:i'), false));
    $printer -> text("No. $data->kode\n");

    $printer -> text("--------------------------------\n");
    
    /* Items */
    $printer -> setJustification(Printer::JUSTIFY_LEFT);
    $printer -> setEmphasis(false);
    foreach ($data->barang as $row) {
        $printer -> text($row->nama . "\n");
        $printer -> text(new item($row->pivot->jumlah . ' x Rp.' . number_format($row->pivot->harga_jual,0,",","."), number_format($row->pivot->jumlah * $row->pivot->harga_jual,0,",","."), false));
    }

    $printer -> text("--------------------------------\n");
    
    /* Tax and total */
    $printer -> text(new item('Total Harga', number_format($data->view->subtotal,0,",","."), false));

    if($data->metode == 'Kredit') {
        $printer -> text(new item('Bayar', '-' . number_format($data->view->subtotal,0,",","."), false));
        $printer -> text(new item('Kembalian', 0, false));
    } else {
        $printer -> text(new item('Bayar', number_format($data->subtotal - $data->dibayar),0,",","."), false);
        $printer -> text(new item('Kembalian', number_format($data->kembalian),0,",","."), false);
    }
    $printer -> selectPrintMode();
    
    $printer -> text("--------------------------------\n");
    
    /* Footer */
    $printer -> setJustification(Printer::JUSTIFY_CENTER);
    $printer -> text("TERIMAKASIH\n");
    $printer -> text("SEMOGA BERKAH DAN BERMAMFAAT\n");
    $printer -> feed(2);
    
    /* Cut the receipt and open the cash drawer */
    $printer -> cut();
    $printer -> pulse();
    
    $printer -> close();
    
    echo "
      <script>
        Window.close();
      </script>
    ";

} catch (\Exception $e) {
  echo $e->getMessage();
}

class item
{
    private $name;
    private $price;
    private $dollarSign;

    public function __construct($name = '', $price = '', $dollarSign = true)
    {
        $this -> name = $name;
        $this -> price = $price;
        $this -> dollarSign = $dollarSign;
    }

    public function __toString()
    {
        $rightCols = 10;
        $leftCols = 20;
        if($this->dollarSign == true) {
            $right = str_pad('Rp. ' . $this -> price, $rightCols, ' ', STR_PAD_LEFT);
        } else {
            $right = str_pad($this -> price, $rightCols, ' ', STR_PAD_LEFT);
        }
        $left = str_pad($this -> name, $leftCols);
        return "$left$right\n";
    }
}