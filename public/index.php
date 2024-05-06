<?php

use PgSql\Result;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';
// Load dependencies

require_once __DIR__ . '/../vendor/autoload.php';

// Create Slim App
$app = AppFactory::create();

// Add Middleware to connect to Database
$app->add(function (Request $request, RequestHandler $handler) use ($app) {
    // Connect to your database (using PDO for example)
    $dsn = "mysql:host=localhost:3308;dbname=pixel";
    $dbusername = "root";
    $dbpass = "";

    try {
        $pdo = new PDO($dsn, $dbusername, $dbpass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Set the PDO instance to be accessible from request attributes
        $request = $request->withAttribute('pdo', $pdo);
    } catch (PDOException $e) {
        // Handle connection errors
        $response = $app->getResponseFactory()->createResponse();
        $response->getBody()->write("Database connection error: " . $e->getMessage());
        return $response->withStatus(500);
    }

    // Call the next middleware handler
    $response = $handler->handle($request);
    $response = $response
    ->withHeader('Access-Control-Allow-Origin', '*')
    ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    return $response;
});

// Define routes
$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello, World!");
    return $response;
});

// Example route accessing the database via middleware
$app->post('/login', function (Request $request, Response $response, $args) {
    $data = json_decode(file_get_contents('php://input'), true);  
    $gmail = $data['Gmail'];
    $pwd = $data['Password'];
    if (strlen($gmail) == 0 && strlen($pwd) == 0) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Username and Password should not be empty']));
        return $response;
    }
    $pdo = $request->getAttribute('pdo');
    $query = "SELECT * FROM registration WHERE gmail= :GMAIL";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':GMAIL', $gmail);
    $stmt->execute();
    if($stmt->execute()==0){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Email or password does not exist. Please register.']));
        return $response;
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hashedPassword = $result['password'];
    if (password_verify($pwd, $hashedPassword)) {
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Welcome '.$result['name']]));
        return $response;
    } else {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Email or password does not exist. Please register.']));
        return $response;
    }
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/registration',function(Request $request,Response $response){
    $data =  json_decode(file_get_contents('php://input'), true);  
    $name = $data['name'];
    $gmail = $data['gmail'];
    $password = $data['password'];
    $confirmpassword = $data['confirmPassword'];
    $pdo = $request->getAttribute('pdo');
    //searching the employee in company db
    $pixelemployeequery = "SELECT * FROM Employee_details WHERE Email_ID = :gmail";
    $stmte = $pdo -> prepare($pixelemployeequery);
    $stmte->bindParam(':gmail', $gmail);
    $stmte -> execute();
    $emails = $stmte -> fetch(PDO::FETCH_ASSOC);
    if($emails==0){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not an pixel employee']));
        return $response;
    }
    //
    $query = "SELECT * FROM registration WHERE gmail = :gmail";
    $search = $pdo->prepare($query);
    $search->bindParam(':gmail', $gmail);
    $search->execute();
    $result_search = $search->fetch(PDO::FETCH_ASSOC);


    if($result_search){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'user already registered']));
        return $response;
    }

    if(strlen($name)==0||strlen($gmail)==0||strlen($password)==0||strlen($confirmpassword)==0){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Fields are should not be empty']));
        return $response;
    }
    if($password!=$confirmpassword){
        $response->getBody()->write(json_encode(['success' => false, 'message' => "password and confirmpassword doesn't match "]));
        return $response;
    }
    function gmail_verify($gmail){
            if (strpos($gmail,'@pixelexpert.net') > 0) {
                return false; 
            } else {
                return true;
        }     
    }
    if(gmail_verify($gmail) == true){
        $response->getBody()->write(json_encode(['success' => false, 'message' => "provide pixel expert email "]));
        return $response;
    }
    
    try{
      
        $query = "INSERT INTO registration(name, gmail, password, confirmPassword) VALUES (:name, :gmail, :password, :confirmPassword);";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':name',$name);
        $stmt->bindParam(':gmail',$gmail);
        $timing=[
            'cost' => 12
        ];
        $hshedpwd=password_hash($password,PASSWORD_BCRYPT,$timing);
        $stmt->bindParam(':password',$hshedpwd);
        $hashedconpwd=password_hash($confirmpassword,PASSWORD_DEFAULT,$timing);
        $stmt->bindParam(':confirmPassword',$hashedconpwd);
        $stmt->execute();
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Successfully Registered']));
    }catch(PDOException $e){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'catcherror'.$e->getMessage()]));

    }
    return $response->withHeader('Content-Type', 'application/json');
    
});


$app->post('/getComponentList',function(Request $request,Response $response){
    $pdo = $request->getAttribute('pdo');
    $data = json_decode(file_get_contents('php://input'), true);  
    $category = $data['category'];
    if($category == 3){
        $query="SELECT c_name, category, COUNT(*) AS no_of_counts FROM main GROUP BY c_name;";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($result)); 
        return $response->withHeader('Content-Type', 'application/json');

    }else{
        $query="SELECT c_name, category, COUNT(*) AS no_of_counts FROM main WHERE category = :category GROUP BY c_name;";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':category',$category);
        $stmt->execute();
        $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    
    }
  
});


// $app->get('/programmablecomponents',function(Request $request,Response $response){
//     $pdo = $request->getAttribute('pdo');
//     $query="SELECT c_name, COUNT(*) AS no_of_counts FROM main WHERE category = 1 GROUP BY c_name;";
//     $stmt=$pdo->prepare($query);
//     $stmt->execute();
//     $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
//     $response->getBody()->write(json_encode($result));
//     return $response->withHeader('Content-Type', 'application/json');
//  });

//  $app->get('/nonprogrammablecomponents',function(Request $request,Response $response){
//     $pdo = $request->getAttribute('pdo');
//     $query="SELECT c_name, category, COUNT(*) AS no_of_counts FROM main WHERE category = 2 GROUP BY c_name;";
//     $stmt=$pdo->prepare($query);
//     $stmt->execute();
//     $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
//     $response->getBody()->write(json_encode($result));
//     return $response->withHeader('Content-Type', 'application/json');
//  });
 
//  $app->get('/allcomponent',function(Request $request,Response $response){
//     $pdo = $request->getAttribute('pdo');
//     $query="SELECT c_name, COUNT(*) AS no_of_counts FROM main GROUP BY c_name;";
//     $stmt=$pdo->prepare($query);
//     $stmt->execute();
//     $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
//     $response->getBody()->write(json_encode($result));
//     return $response->withHeader('Content-Type', 'application/json');
//  });


 $app->post('/forgetPassword',function(Request $request,Response $response){
    $pdo = $request->getAttribute('pdo');
    $data =  json_decode(file_get_contents('php://input'), true);  
    $id = $data['employee_id'];  
     $query = "SELECT * from Employee_details WHERE Employee_ID=:empid;";
     $stmt = $pdo->prepare($query);
     $stmt->bindParam(':empid',$id);
     $stmt->execute();
     $result = $stmt->fetch(PDO::FETCH_ASSOC);
     if($result){
         $response->getBody()->write(json_encode(['success' => TRUE, 'message' => 'Employee ID matched ' .$result['Employee_Name']]));
         return $response->withHeader('Content-Type', 'application/json');
     }

});


$app->post('/updatePassword',function(Request $request,Response $response){
    $pdo = $request->getAttribute('pdo');
    $data =  json_decode(file_get_contents('php://input'), true);  
    $email = $data['email'];
    $password = $data['password'];
    $confirmpassword = $data['confirmpassword'];
    if($password!=$confirmpassword){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Password and Confirm Password does not match']));
        return $response;
    }
    if(strlen($email)==0||strlen($password)==0||strlen($confirmpassword)==0){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Fields are should not be empty']));
        return $response;
    }
    $query = "UPDATE registration SET password = :Password WHERE gmail = :Gmail";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':Gmail',$email);
    $timing=[
        'cost' => 12
    ];
    $hshedpwd=password_hash($password,PASSWORD_BCRYPT,$timing);
    $stmt->bindParam(':Password',$hshedpwd);
    $stmt->execute();
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Password updated']));
        return $response;
   
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Password updation failed']));
        return $response;
    return $response->withHeader('Content-Type', 'application/json');

});


$app->post('/email',function(Request $request,Response $response){
    $data =  json_decode(file_get_contents('php://input'), true);  
    $owner = $data['name'];
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'pixelexperttechnology@gmail.com';                     //SMTP username
    $mail->Password   = 'azdxvitwcroyplet';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
    $mail->Port       = 465; 
    $mail->setFrom('subashparthiban2@gmail.com', $owner);
    $mail->addAddress('karthik.s@pixelexpert.net', 'karthik');     //Add a recipient
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = $data['subject'];
    $mail->Body = 'Hi Kalaichelvi Dhandapani <br> Below is the component list that I want, Please check and give the approval<br><br><br>';
    $i=1;
    foreach ($data['body'] as $components) {
            $mail->Body .= $i.'. '.$components.'<br>';
            $i++;
    }
    

    $mail->Body .= '<br>Thanks<br>' . $data['name'];
       
    $mail->send();
    if($mail){
     $response->getBody()->write(json_encode(['success' => true, 'message' => 'Successfully send']));
       return $response;
    }else{
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'unsuccess']));
        return $response;

    }
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
