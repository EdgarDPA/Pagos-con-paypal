<?php

namespace App\Http\Controllers;
//se llaman a todas las clases de la API de Paypal a utilizar
use Illuminate\Http\Request;
use PayPal\Rest\ApiContext;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\ExecutePayment;
use PayPal\Api\PaymentExecution;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Sale;

use App\OrdenCompra;

class PaypalController extends Controller
{

  private $_api_context;

  public function __construct()
  {
    // se utiliza el archivo de configuracion Paypal en config
    $paypal_conf = config('paypal');
    $this->_api_context = new ApiContext(new OAuthTokenCredential($paypal_conf['client_id'], $paypal_conf['secret']));
    $this->_api_context->setConfig($paypal_conf['settings']);
  }

  	// se va a configurar todo lo que se envia a paypal.
	public function postPayment()
	{
		$payer = new Payer(); // va a contener todo lo del pago
		$payer->setPaymentMethod('paypal'); // seleciona el metodo de pago en paypal
		
		// se crea el producto 
		$item1 = new Item();
		$item1->setName('curso de android')//nombre
		->setDescription('40 videos de curso de desarrollo de android')//descripcion
    	->setCurrency('MXN')//moneda
    	->setQuantity(1)//cantidad
    	->setPrice(10);//precio

    	// se crea lista de productos a mostrar 
    	$itemList = new ItemList();
		$itemList->setItems(array($item1));

		// se establece monto a pagar
		$amount = new Amount();
		$amount->setCurrency("MXN")
  		->setTotal(10);
		
  		//pasa todo los objetos del pago en una trancacion a paypal 
		$transaction = new Transaction();
		$transaction->setAmount($amount)
		 ->setItemList($itemList)
    	->setDescription("pagos de prueba en quetzaledu");

		//se redirecciona hacia el mismo sitio 
		$redirect_urls = new RedirectUrls();
		$redirect_urls->setReturnUrl(\URL::route('payment.status'))
			->setCancelUrl(\URL::route('payment.status'));

			//se crea el metodo de pago
		$payment = new Payment();
		$payment->setIntent('Sale') // este valor es para una venta directa 
			->setPayer($payer)
			->setRedirectUrls($redirect_urls)
			->setTransactions(array($transaction));

			//hacemos la coneccion a la API de PAYPAL
		try {
			$payment->create($this->_api_context);
		} catch (\PayPal\Exception\PPConnectionException $ex) {
			if (\Config::get('app.debug')) {
				echo "Exception: " . $ex->getMessage() . PHP_EOL;
				$err_data = json_decode($ex->getData(), true);
				exit;
			} else {
				die('Ups! Algo salió mal');
			}
		}


		// recibimos url de aprobacion para redirigir al usuario a paypal y completar trancaccion 	
		foreach($payment->getLinks() as $link) {
			if($link->getRel() == 'approval_url') {
				$redirect_url = $link->getHref();
				break;
			}
		}


		// agregamos el ID del usuario para dar seguimiento a la trancaccion 
		\Session::put('paypal_payment_id', $payment->getId());
		if(isset($redirect_url)) {
			// redirect to paypal
			return \Redirect::away($redirect_url); // se redireciona a paypal para que el usuario pueda aceptar el pago
		}
		return \Redirect::route('compras')
			->with('error', 'Error no se pudo redirecionar a paypal');
	}

// este metodo dara la respuesta de paypal 
public function getPaymentStatus(Request $request)
	{
	// Get the payment ID before session clear
    $payment_id = \Session::get('paypal_payment_id');

    // clear the session payment ID
    \Session::forget('paypal_payment_id');
    
   
    if (empty($request->input('PayerID')) || empty($request->input('token'))) {
      dd('algo esta fallando');
      return redirect('/payment/add-funds/paypal');
    }
    
    $payment = Payment::get($payment_id, $this->_api_context);
    
    // PaymentExecution object includes information necessary
    // to execute a PayPal account payment.
    // The payer_id is added to the request query parameters
    // when the user is redirected from paypal back to your site
    $execution = new PaymentExecution();
    $execution->setPayerId($request->input('PayerID'));
    
    //Execute the payment
    $result = $payment->execute($execution, $this->_api_context);
    
    if ($result->getState() == 'approved') { // payment made
      // Payment is successful do your business logic here
      //dd($result); 
    	$transactions = $payment->getTransactions();
		$relatedResources = $transactions[0]->getRelatedResources();
		$sale = $relatedResources[0]->getSale();
	
    	$ordenCompra = OrdenCompra::create([
	        'id_compra_paypal' => $result->getId(),
	        'inten' => $sale->state,
	        'state' => $result->state,
	        'cart' => $result->cart,
	        'payment_method' => $sale->payment_mode,
	        'total' => $sale->amount
	        
	    ]);
      
     
      return redirect('comprasatisfactoria');
    }
    
   dd('pago fallido');
    return redirect('/payment/add-funds/paypal');
  }
	
	



 }