<?php

namespace App\Command;

use App\Entity\Country;
use App\Entity\Gender;
use App\Entity\Metric;
use DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use phpseclib3\Net\SFTP;
use Doctrine\Persistence\ManagerRegistry;

#[AsCommand(
    name: 'app:download-process-data',
    description: 'Add a short description for your command',
)]
class DownloadAndProcessDataCommand extends Command
{
    private $doctrine;

    // Inyección de dependencias de ManagerRegistry
    public function __construct(ManagerRegistry $doctrine)
    {

        parent::__construct();
        $this->doctrine = $doctrine;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. Obtener la fecha actual en formato YYYYMMDD
        $date = date('Ymd');

        // 2. Definir la URL de la API y el nombre del archivo de salida
        $url = 'https://dummyjson.com/users';
        $jsonFileName = "data_{$date}.json";

        // 3. Descargar el JSON usando HttpClient
        $client = HttpClient::create();
        $response = $client->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            $output->writeln('Error Obteniendo datos de la API');
            return Command::FAILURE;
        }

        $jsonData = $response->getContent();

        // 4. Guardar el archivo JSON
        $filesystem = new Filesystem();
        $directory = __DIR__ . '/../../public/data'; // Carpeta donde se guardará el archivo
        $filesystem->mkdir($directory);

        $filePath = "{$directory}/{$jsonFileName}";
        $filesystem->dumpFile($filePath, $jsonData);

        $output->writeln("Json Descargado y Guardado: {$filePath}");

        // 5. Convertir el JSON descargado a CSV

        $jsonArray = json_decode($jsonData, true);
        $csvFileName = "etl_{$date}.csv";
        $csvFilePath = "{$directory}/{$csvFileName}";

        //  $this->jsonToCsv($jsonArray,$csvFilePath);
        $csvFile = fopen($csvFilePath, 'w');


        // Obtener las claves aplanadas para los encabezados desde el primer usuario
        $headers = $this->flattenArray(array_keys($this->flattenArray($jsonArray['users'][0], true)));

        // Escribir los encabezados en el archivo CSV
        fputcsv($csvFile, $headers);

        // Iterar sobre cada usuario en el array 'users'
        foreach ($jsonArray['users'] as $user) {
            // Aplanar los datos del usuario
            $flattenedUser = $this->flattenArray($user);

            // Escribir los datos aplanados en el archivo CSV
            fputcsv($csvFile, array_values($flattenedUser));
        }

        // Cerrar el archivo CSV
        fclose($csvFile);
        $output->writeln("CSV data generada: {$csvFilePath}");

        // 6. Generar estadísticas y crear el archivo summary_[YYYYMMDD].csv
        $summaryFileName = "summary_{$date}.csv";
        $summaryFilePath = "{$directory}/{$summaryFileName}";

        $totalUsers = count($jsonArray['users']);
        $cityCount = [];
        $age28Count = 0;
        $maleCount = 0;
        $femaleCount = 0;
        $otherCount = 0;

        foreach ($jsonArray['users'] as $user) {

            $city = $user['address']['city'];
            if (!isset($cityCount[$city])) {
                $cityCount[$city] = 0;
            }
            $cityCount[$city]++;

            if ($user['gender'] === "male") {
                $maleCount++;
            } elseif ($user['gender'] === "female") {

                $femaleCount++;
            } else {

                $otherCount++;
            }
        }


        $summaryFile = fopen($summaryFilePath, 'w');
        fputcsv($summaryFile, ['Metric', 'Value']);
        fputcsv($summaryFile, ['Total Users', $totalUsers]);
        foreach ($cityCount as $cCity => $count) {
            fputcsv($summaryFile, ["City Count {$cCity}", $count]);
        }
        fputcsv($summaryFile, ['Males', $maleCount]);
        fputcsv($summaryFile, ['Females', $femaleCount]);
        fputcsv($summaryFile, ['Other', $otherCount]);
        fclose($summaryFile);
        $output->writeln("CSV de Estadisticas guardado en: {$summaryFilePath}");

        //guardar estadisticas en la base de datos 

      
        $doctrine = $this->doctrine->getManager();

        $metric = new Metric();
        $metric->setMetric("Total Users");
        $metric->setValue($totalUsers);
        $metric->setDate(new DateTime());
        $doctrine->persist($metric);

        $cntMales = new Gender();
        $cntMales->setGender("Males");
        $cntMales->setValue($maleCount);
        $cntMales->setIdMetric($metric);
        $doctrine->persist($cntMales);

        $cntFemales = new Gender();
        $cntFemales->setGender("Females");
        $cntFemales->setValue($femaleCount);
        $cntFemales->setIdMetric($metric);
        $doctrine->persist($cntFemales);

        $cntOther = new Gender();
        $cntOther->setGender("Other");
        $cntOther->setValue($otherCount);
        $cntOther->setIdMetric($metric);
        $doctrine->persist($cntOther);

        foreach ($cityCount as $cCity => $count) {

            $city = new Country();
            $city->setCountry($cCity);
            $city->setValue($count);
            $city->setIdMetric($metric);
            $doctrine->persist($city);
        }

        $doctrine->flush();



        $output->writeln("Metricas Guardadas en la Base de datos");

         // Cargar variables de entorno
         $sftpHost = $_ENV['SFTP_HOST'];    // Host del servidor SFTP
         $sftpPort = $_ENV['SFTP_PORT'];    // Puerto del servidor SFTP (por defecto es 22)
         $sftpUser = $_ENV['SFTP_USER'];    // Usuario del SFTP
         $sftpPass = $_ENV['SFTP_PASS'];    // Contraseña del SFTP
         $remoteDir = $_ENV['SFTP_REMOTE_DIR'];  // Directorio remoto donde se guardarán los archivos
         
         // Ruta de los archivos locales
         $localDir = __DIR__ . '/../../public/data';  // Asegúrate de cambiar esta ruta
 
         $files = [
             'data_' . date('Ymd') . '.json',
             'etl_' . date('Ymd') . '.csv',
             'summary_' . date('Ymd') . '.csv'
         ];
 
         // Crear conexión SFTP
         $sftp = new SFTP($sftpHost, $sftpPort);
 
         // Intentar la conexión y autenticación
         if (!$sftp->login($sftpUser, $sftpPass)) {
             exit('Error: No se pudo autenticar en el servidor SFTP.');
         }
 
         // Cambiar al directorio remoto donde se subirán los archivos
         if (!$sftp->chdir($remoteDir)) {
             exit('Error: No se pudo cambiar al directorio remoto.');
         }
 
         // Subir cada archivo
         foreach ($files as $file) {
             $localFilePath = $localDir . $file;
             
             // Verificar si el archivo existe localmente
             if (!file_exists($localFilePath)) {
                 echo "El archivo $localFilePath no existe.\n";
                 continue;
             }
 
             // Subir el archivo
             if (!$sftp->put($file, $localFilePath, SFTP::SOURCE_LOCAL_FILE)) {
                 echo "Error al subir el archivo $file\n";
             } else {
                 echo "Archivo $file subido correctamente.\n";
             }
         }

   


        return Command::SUCCESS;
    }

    // Función para aplanar arrays anidados
    private function flattenArray($array, $prefix = '')
    {
        $result = [];

        foreach ($array as $key => $value) {
            // Si el valor es un array, llamamos recursivamente a flattenArray
            if (is_array($value)) {
                $newPrefix = $prefix ? $prefix . '.' . $key : $key;
                $result = array_merge($result, $this->flattenArray($value, $newPrefix));
            } else {
                // Si no es un array, lo agregamos al resultado con el prefijo adecuado
                $newKey = $prefix ? $prefix . '.' . $key : $key;
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
