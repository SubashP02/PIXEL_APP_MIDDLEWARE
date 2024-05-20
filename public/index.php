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
    $query = "SELECT *,ed.role,ed.Employee_Name FROM registration as r INNER JOIN Employee_details as ed ON r.gmail = ed.Email_ID WHERE gmail= :GMAIL";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':GMAIL', $gmail);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if($stmt->execute()==0){
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Email or password does not exist. Please register.']));
        return $response;
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hashedPassword = $result['password'];
    if (password_verify($pwd, $hashedPassword)) {
        // $response->getBody()->write(json_encode($result['name']));
        $response->getBody()->write(json_encode(['success' => true, 'role' => $result['role'], 'name' => $result['Employee_Name']]));
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
        $query="SELECT tc.c_name,tc.no_of_counts,m.asset_no,m.status FROM total_components as tc INNER JOIN main as m on tc.c_name = m.c_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($result)); 
        return $response->withHeader('Content-Type', 'application/json');

     }
    else{
       $query="SELECT c_name,asset_no,status FROM main WHERE category = :category AND status = 'Available'";
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


// $app->post('/email',function(Request $request,Response $response){
//     $data =  json_decode(file_get_contents('php://input'), true);  
//     $owner = $data['name'];
//     $mail = new PHPMailer(true);
//     $mail->isSMTP();
//     $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
//     $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
//     $mail->Username   = 'pixelexperttechnology@gmail.com';                     //SMTP username
//     $mail->Password   = 'azdxvitwcroyplet';                               //SMTP password
//     $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
//     $mail->Port       = 465; 
//     $mail->setFrom('subashparthiban2@gmail.com', $owner);
//     $mail->addAddress('karthik.s@pixelexpert.net', 'karthik');     //Add a recipient
//     $mail->isHTML(true);                                  //Set email format to HTML
//     $mail->Subject = $data['subject'];
//     $mail->Body = 'Hi Subashini Purushothaman <br> Below is the component list that I want, Please check and give the approval<br><br>';
//     $length = sizeof($data['body']);
//     for($i=0,$j=1;$i<$length;$i++,$j++){
//         $count_string=$data['count'][$i]==1?'count':'counts';
//         $mail->Body .=$j.'. '.$data['body'][$i]. ' -> ' . $data['count'][$i] .' ' .$count_string.'<br>';
//         $pdo = $request->getAttribute('pdo');
//         $query = "INSERT INTO `admin_view`(`components`, `quantity`, `flag`) VALUES (:components, :quantity, 1); ";
//         $stmt = $pdo->prepare($query);
//         $stmt->bindParam(':components',$data['body'][$i]);
//         $stmt->bindParam(':quantity',$data['count'][$i]);
//         $stmt->execute();
//     }
//     $mail->Body .= '<br>Thanks<br>' . $data['name'];
       
//     $mail->send();
//     if($mail){
//      $response->getBody()->write(json_encode(['success' => true, 'message' => 'Successfully send']));
//        return $response;
//     }else{
//         $response->getBody()->write(json_encode(['success' => false, 'message' => 'unsuccess']));
//         return $response;

//     }
//     return $response->withHeader('Content-Type', 'application/json');
// });


$app->post('/email',function(Request $request,Response $response){
    $data =  json_decode(file_get_contents('php://input'), true);  
    $owner = $data['employee_name'];
    $pdo = $request->getAttribute('pdo');
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'pixelexperttechnology@gmail.com';                     //SMTP username
    $mail->Password   = 'azdxvitwcroyplet';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
    $mail->Port       = 465; 
    $mail->setFrom('subashparthiban2@gmail.com', $owner);
    $mail->addAddress('subashini.p@pixelexpert.net', 'Subashini');     //Add a recipient
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = $data['request'];
    $mail->Body = 'Hi Subashini Purushothaman <br> Below is the component list that I want, Please check and give the approval<br><br>';
    $componentlist = sizeof($data['component_list']);
    // for($i=0,$j=1;$i<$length;$i++,$j++){
    //     $query  = "SELECT no_of_counts,c_name from main where asset_no = :asset_id ";
    //     $stmt = $pdo->prepare($query);
    //     $stmt->bindParam(':asset_id',$data['component_list'][$i]['asset_id'][$i]);
    //     $stmt->execute();
    //     $result = $stmt->fetchAll();
    //     $count = $result[0]['no_of_counts'];
    // }
    // if($count==0){
    //     $response->getBody()->write(json_encode(['success' => false, 'message' => 'selected components are not available']));
    // }else{

        for ($i = 0, $j = 1; $i < $componentlist; $i++) {
            $assetlength = sizeof($data['component_list'][$i]['asset_id']);
            for ($z = 0; $z < $assetlength; $z++, $j++) {
                $mail->Body .= $j . '. ' . $data['component_list'][$i]['component_name'] . '   ->' . $data['component_list'][$i]['asset_id'][$z] . '<br>';
                $query = "INSERT INTO `admin_view`(components,asset_id, flag,employee_name) VALUES (:components, :asset_id, 1,:employee_name); ";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':components', $data['component_list'][$i]['component_name']);
                $stmt->bindParam(':asset_id', $data['component_list'][$i]['asset_id'][$z]);
                $stmt->bindParam(':employee_name', $data['employee_name']);
                $stmt->execute();
            }
        }
        
        
    $mail->Body .= '<br>Thanks<br>' . $data['employee_name'];
       
    $mail->send();
    if($mail){
     $response->getBody()->write(json_encode(['success' => true, 'message' => 'Successfully send']));
       return $response;
    }else{
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'unsuccess']));
        return $response;

    }
// }
    return $response->withHeader('Content-Type', 'application/json');

});

$app->get('/adminlistview',function(Request $request,Response $response){
    $pdo = $request->getAttribute('pdo');
    $query = "SELECT DISTINCT employee_name FROM admin_view where flag = 1;";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll();
    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});


// // 
// $app->post('/adminbackend',function(Request $request,Response $response){
//     $pdo = $request->getAttribute('pdo');
//     $data =  json_decode(file_get_contents('php://input'), true);
//     $name = $data['c_name'];
//     $request_count = $data['count'];
//     $querry = "SELECT * from total_components where c_name = :c_name";
//     $stmt =$pdo->prepare($querry);
//     $stmt->bindParam(':c_name',$name);
//     $stmt->execute();
//     $result = $stmt->fetch(PDO::FETCH_ASSOC);
//     $old_count = $result['no_of_counts'];
//     $new_count = ($old_count-$request_count);
//     $querry = "UPDATE total_components SET no_of_counts = :new_count WHERE c_name = :c_name";
//     $stmt =$pdo->prepare($querry);
//     $stmt->bindParam(':new_count',$new_count);
//     $stmt->bindParam(':c_name',$name);
//     $stmt->execute();
//     $response->getBody()->write(json_encode(['success' => true, 'message' => 'components approved']));
//     return $response;
//     });

$app->post('/admindashboard',function(Request $request,Response $response){
    $data =  json_decode(file_get_contents('php://input'), true);  
    $name = $data['name'];
    $pdo = $request->getAttribute('pdo');
    $querry = "SELECT admin_view.components,admin_view.asset_id,admin_view.change_status, total_components.no_of_counts FROM admin_view JOIN total_components ON admin_view.components = total_components.c_name where flag=1 and employee_name = :name;";
    $stmt =$pdo->prepare($querry);
    $stmt->bindParam(':name',$name);
    $stmt->execute();
    $final_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($final_result));
    return $response->withHeader('Content-Type', 'application/json');
});

// $app->post('/admin_status_change',function(Request $request,Response $response){
//     $pdo = $request->getAttribute('pdo');
//     $data =  json_decode(file_get_contents('php://input'), true);
//     $length = sizeof($data['components']);
//     for($i=0;$i<$length;$i++){
//         $querry = "UPDATE admin_view AS av
//         JOIN total_components AS tc ON av.components = tc.c_name
//         JOIN main AS mt ON av.components = mt.c_name -- Assuming component_name is the column in main_table to join with components in admin_view
//         SET 
//             av.change_status = CASE WHEN av.components = :components THEN :pressed_status ELSE av.change_status END,
//             av.flag = CASE WHEN av.components = :components THEN 0 ELSE av.flag END,
//             tc.no_of_counts = tc.no_of_counts - :user_entered_count,
//             mt.status = CASE WHEN mt.asset_no = :user_asset_id THEN :new_status_value ELSE mt.status END
//  -- Assuming :new_status_value is the new status value you want to update
//         WHERE av.components = :components;
//         ";
//         $stmt = $pdo->prepare($querry);
//         $stmt->bindParam(':components',$data['components'][$i]);
//         $stmt->bindParam(':pressed_status',$data['status'][$i]);
//         $stmt->bindParam(':user_asset_id',$data['user_asset_id'][$i]);
//         $stmt->bindParam(':user_entered_count',$data['user_entered_count'][$i]);
//         $stmt->execute();
//     }
//     $response->getBody()->write(json_encode(['success' => true, 'message' => 'components provided']));
//     return $response->withHeader('Content-Type', 'application/json');

// });



$app->post('/admin_status_change',function(Request $request,Response $response){
    $pdo = $request->getAttribute('pdo');
    $data =  json_decode(file_get_contents('php://input'), true);
    $length = sizeof($data['components_details']);
    // print_r($length);
    for($i=0;$i<$length;$i++){
        $querry = "UPDATE main as m
        JOIN admin_view AS av ON m.c_name = av.components
        SET m.no_of_counts = m.no_of_counts - 1,
            m.status = :pressed_status,
            av.flag = 0
        WHERE m.asset_no = :user_asset_id;
        CREATE TRIGGER update_total_components
AFTER UPDATE ON main
FOR EACH ROW
BEGIN
    IF OLD.no_of_counts != NEW.no_of_counts THEN
        UPDATE total_components tc
        JOIN main m ON tc.c_name = m.c_name
        SET tc.no_of_counts = tc.no_of_counts - 1
        WHERE m.asset_no = NEW.asset_no;
    END IF;
END;

        ";
        $stmt = $pdo->prepare($querry);
        $stmt->bindParam(':pressed_status',$data['components_details'][$i]['status']);
        $stmt->bindParam(':user_asset_id',$data['components_details'][$i]['user_asset_id']);
        $stmt->execute();
    }
    $response->getBody()->write(json_encode(['success' => true, 'message' => 'components provided']));
    return $response->withHeader('Content-Type', 'application/json');

});

$app->run();
