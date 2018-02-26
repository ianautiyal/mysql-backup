<?php

use Aws\S3\S3Client;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

class MoveAndUpdate
{

    public $today;


    public function __construct()
    {
        $this->today = date('d M Y h:i:s A');
    }

    public function getS3Client()
    {
        return new S3Client([
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY')
            ],
            'region' => getenv('AWS_DEFAULT_REGION'),
            'version' => getenv('AWS_API_VERSION')
        ]);
    }

    public function getMountManager()
    {
        $s3Adapter    = new AwsS3Adapter($this->getS3Client(), getenv('AWS_BUCKET'), getenv('REMOTE_BACKUP_DIR'));
        $s3Filesystem = new Filesystem($s3Adapter);

        $localAdapter    = new Local(__DIR__. '/' .getenv('LOCAL_BACKUP_DIR'));
        $localFilesystem = new Filesystem($localAdapter);

        return new MountManager([
            'local' => $localFilesystem,
            'storage' => $s3Filesystem
        ]);
    }

    public function moveDataToS3() {
        $manager = $this->getMountManager();
        $databases = $manager->listContents('local://', true);
        $basedir = str_replace(' ', '_', str_replace(':', '_', $this->today));

        $return = [
            'dbcount' => 0,
            'totalsize' => 0,
            'databases' => $databases
        ];

        foreach ($databases as $key => $database) {
            if ($manager->copy('local://'.$database['path'], 'storage://'. $basedir . '/'.$database['path'])) {
                $return['databases'][$key]['status'] = 'OK';
            } else {
                $return['databases'][$key]['status'] = 'Error';
            }
            $return['dbcount']++;
            $return['totalsize'] += $database['size'];
        }
        return $return;
    }

    public function getGoogleClient() {
        $client = new Google_Client();
        $client->setScopes([
            Google_Service_Sheets::SPREADSHEETS
        ]);
        $client->setAuthConfig( __DIR__ . '/' . getenv('GOOGLE_CLIENT_SECRET') );
        $client->setApplicationName(getenv('APP_NAME'));
        return $client;
    }

    public function makeSheets_Request($sheetId, $sheetData) {
        $values = [];
        foreach( $sheetData as $key => $row ) {
            $cellData = new Google_Service_Sheets_CellData();
            $value = new Google_Service_Sheets_ExtendedValue();
            $value->setStringValue($row);
            $cellData->setUserEnteredValue($value);
            $values[] = $cellData;
        }
        $rowData = new Google_Service_Sheets_RowData();
        $rowData->setValues($values);
        $append_request = new Google_Service_Sheets_AppendCellsRequest();
        $append_request->setSheetId($sheetId);
        $append_request->setRows($rowData);
        $append_request->setFields('userEnteredValue');
        $request = new Google_Service_Sheets_Request();
        $request->setAppendCells($append_request);

        return $request;
    }

    public function updateSpreadsheet($sheetContent) {
        $requests = [];

        $sheet1Data = [
            'datetime' => $this->today,
            'servername' => getenv('REMOTE_BACKUP_DIR'),
            'dbcount' => (string) $sheetContent['dbcount'],
            'toalsize' => (string) $sheetContent['totalsize'],
            'status' => 'OK'
        ];

        $requests[] = $this->makeSheets_Request(getenv('WORKSHEET1'), $sheet1Data);
        $basedir = str_replace(' ', '_', str_replace(':', '_', $this->today));

        if (count($sheetContent['databases'])) {
            foreach ($sheetContent['databases'] as $key => $database) {
                $sheet2Data = [
                    'datetime' => $this->today,
                    'servername' => getenv('REMOTE_BACKUP_DIR'),
                    'database' => $database['basename'],
                    'path' => getenv('REMOTE_BACKUP_DIR') . '/' . $basedir . '/' . $database['path'],
                    'size' => (string) $database['size'],
                    'status' => $database['status']
                ];
                $requests[] = $this->makeSheets_Request(getenv('WORKSHEET2'), $sheet2Data);
            }
        }

        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        $sheet_service = new Google_Service_Sheets($this->getGoogleClient());
        $response = $sheet_service->spreadsheets->batchUpdate(getenv('SPREADSHEET'), $batchUpdateRequest);

        return $response->valid();
    }
}
