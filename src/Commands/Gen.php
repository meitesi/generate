<?php

namespace Meitesi\Generate\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * 代码生成工具
 */
class Gen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gen:curd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '代码生成工具';
    private ProgressBar $processBar;


    public function handle()
    {
        $name = $this->ask('请输入表名');
        $tableComment = $this->ask('请输入表名备注');
        if (!Schema::hasTable(Str::snake($name))) { // 数据库表不存在
            if (!$this->migrationFileExits(Str::snake($name))) { //迁移文件不存在
                $this->migration(Str::snake($name),$tableComment);
            }
            $bar = $this->output->createProgressBar(1);
            $this->info("迁移文件执行中...");
            $bar->start();
            Artisan::call('migrate');
            $bar->finish();
            $this->newLine();
            $this->info("迁移文件执行成功");
            $this->newLine();
        }
        $this->processBar = $this->output->createProgressBar(9);
        $file_path = $this->choice('请选择保存模块', ['Admin', 'Api'], 'Admin');
        $this->info("模板文件创建中...");
        $this->processBar->start();
        $name = Str::studly($name);
        $this->controller($name,$file_path);
        $this->model($name);
        $this->filter($name);
        $this->request($name,$file_path,'Create');
        $this->request($name,$file_path,'Update');
        $this->request($name,$file_path,'Show');
        $this->request($name,$file_path,'Delete');
        $this->service($name,$file_path);
        $this->route($name,$file_path);
        $this->processBar->finish();
        $this->newLine();
        $this->info('模板文件生成成功!');
    }

    /**
     * 生成迁移文件
     * @param $name
     * @param $tableName
     * @return void
     */
    public function migration($name,$tableName): void
    {
        $migration = $this->getSchema();
        $migrationTemplate = str_replace(
            [
                '{{modelName}}',
                '{{tableComment}}',
                '{{schemaUp}}'
            ],
            [
                $name,
                $tableName,
                $migration,
            ],
            $this->getStub('Migration')
        );
        $fileName = date("Y_m_d_His",time()).'_create_'.$name;
        file_put_contents(base_path("/database/migrations/{$fileName}.php"), $migrationTemplate);
    }

    /**
     * 生成控制器
     * @param $name
     * @param $file_path
     * @return void
     */
    protected function controller($name,$file_path): void
    {
        $controllerTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNameSingularLowerCase}}',
                '{{filePath}}'
            ],
            [
                $name,
                Str::camel($name),
                $file_path,
            ],
            $this->getStub('Controller')
        );

        if(!file_exists($path = app_path('/Http/Controllers/'.$file_path)))
            mkdir($path, 0777, true);

        file_put_contents(app_path("/Http/Controllers/{$file_path}/{$name}Controller.php"), $controllerTemplate);
        $this->processBar->advance();
    }

    /**
     * 生成模型
     * @param $name
     * @return void
     */
    protected function model($name): void
    {
        $fillAble=$this->getFillAble($name);
        $property=$this->getProperty($name);
        $modelTemplate = str_replace(
            ['{{modelName}}','{{tableName}}','{{property}}','{{fillAble}}'],
            [$name,Str::snake($name),$property,$fillAble],
            $this->getStub('Model')
        );
        file_put_contents(app_path("/Models/{$name}.php"), $modelTemplate);
        $this->processBar->advance();
    }

    /**
     * 生成查询文件
     * @param $name
     * @return void
     */
    protected function filter($name): void
    {
        $filterTemplate = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Filter')
        );
        if(!file_exists($path = app_path('/Models/Filters')))
            mkdir($path, 0777, true);

        file_put_contents(app_path("/Models/Filters/{$name}Filter.php"), $filterTemplate);
        $this->processBar->advance();
    }

    /**
     * 生成请求文件
     * @param $name
     * @param $file_path
     * @param $method
     * @return void
     */
    protected function request($name,$file_path,$method): void
    {
        $property=$this->getProperty($name);
        $requestTemplate = str_replace(
            ['{{modelName}}','{{filePath}}','{{method}}','{{property}}'],
            [$name,$file_path,$method,$property],
            $this->getStub('Request')
        );
        if(!file_exists($path = app_path('/Http/Requests/'.$file_path.'/'.$name)))
            mkdir($path, 0777, true);

        file_put_contents(app_path("/Http/Requests/{$file_path}/{$name}/{$name}{$method}Request.php"), $requestTemplate);
        $this->processBar->advance();
    }

    /**
     * 生成路由文件
     * @param $name
     * @param $file_path
     * @return void
     */
    public function route($name,$file_path)
    {
        $routeTemplate = str_replace([
            '{{prefix}}',
            '{{routeName}}',
            '{{prefixSnake}}',
        ],[
            $file_path,
            $name,
            Str::snake($name,'-')
        ],$this->getStub('Route'));
        File::append(base_path('routes/'.strtolower($file_path).'.php'),$routeTemplate);
        $this->processBar->advance();
    }

    /**
     * 生成服务逻辑
     * @param $name
     * @param $file_path
     * @return void
     */
    protected function service($name,$file_path): void
    {
        $serviceTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNameSingularLowerCase}}',
                '{{filePath}}'
            ],
            [
                $name,
                Str::camel($name),
                $file_path,
            ],
            $this->getStub('Service')
        );

        if(!file_exists($path = app_path('/Services/'.$file_path)))
            mkdir($path, 0777, true);

        file_put_contents(app_path("/Services/{$file_path}/{$name}Service.php"), $serviceTemplate);
        $this->processBar->advance();
    }

    protected function getStub($type): bool|string
    {
        return file_get_contents(resource_path("stubs/$type.stub"));
    }


    /**
     * 获取模型字段注释
     * @param $name
     * @return string
     */
    private function getProperty($name): string
    {
        //数据库字段
        $columns = $this->getDatabaseInfo($name);
        if (empty($columns)) {
            return "";
        }
        $str ="/**".PHP_EOL;
        foreach ($columns as $column){
            $str.=" * @property ". $this->handleType($column['data_type'])." ".$column['column_name']." ".$column['column_comment'].PHP_EOL;
        }
        $str.=" */";
        return $str;
    }

    /**
     * 获取模型填充字段
     * @param $name
     * @return string
     */
    private function getFillAble($name): string
    {
        //数据库字段
        $columns = $this->getDatabaseInfo($name);
        if (empty($columns)) {
            return "";
        }
        $str ="";
        foreach ($columns as $column){
            $str.= "'".$column['column_name']."',";
        }
        return substr($str,0,-1);
    }


    /**
     * 获取数据库字段
     * @param $name
     * @return bool|array
     */
    private function getDatabaseInfo($name): bool|array
    {
        if (empty($name)) {
            return false;
        }
        $column=[];
        $tableName = Str::snake($name);
        //数据库名称
        $database = \DB::connection()->getDatabaseName();
        $list = \DB::select("select COLUMN_NAME,DATA_TYPE,COLUMN_COMMENT from information_schema.COLUMNS where table_name = '".$tableName."' and table_schema = '".$database."'");
        if (!empty($list)) {
            foreach ($list as $item){
                $column[]=[
                    'column_name'=>$item->COLUMN_NAME,
                    'data_type'=>$item->DATA_TYPE,
                    'column_comment'=>$item->COLUMN_COMMENT,
                ];
            }
        }
        return $column;
    }

    /**
     * 处理 数据库字段类型
     * @param $type
     * @return string
     */
    private function handleType($type): string
    {
        if (in_array($type,['bigint','tinyint','integer','smallint','int'])) {
            return "int";
        }elseif(in_array($type,['varchar','char','text'])) {
            return "string";
        }elseif($type == 'timestamp') {
            return "Carbon";
        }
        return "string";
    }

    /**
     * 判断迁移文件是否存在
     * @param $name
     * @return bool
     */
    private function migrationFileExits($name): bool
    {
        $filesystem = new Filesystem();
        $migrateFileName = 'create_'.$name;
        $files = $filesystem->allFiles('./database/migrations/');
        foreach ($files as $file) {
            if (strpos($file->getFilename(), $migrateFileName) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成 迁移文件
     * @return string
     */
    private function getSchema(): string
    {
        $index=0;
        $schemaData=$space='';
        do {
            $str='$table->';
            $column = $this->ask('字段名');
            $type = $this->choice('字段类型', ['integer', 'string','decimal','tinyInteger','text','timestamp']);
            if ($type=='decimal') {
                $decimal = $this->ask('保留小数位',2);
                $str.= $type.'(\''.$column.'\','.$decimal.')->';
            }else{
                $str.= $type.'(\''.$column.'\')->';
            }
            $nullable = $this->confirm('字段可空');
            $nullable = $nullable?'true':'false';
            $str.='nullable('.$nullable.')->';
            if (!in_array($type,['text','timestamp'])) {
                $default = $this->ask('字段默认值',$this->getDefault($type));
                $str.='default('.$default.')->';
            }
            $comment = $this->ask('字段注释','');
            $str.='comment(\''.$comment.'\');';
            if ($index > 0) {
                $space='            ';
            }
            $schemaData.=$space.$str.PHP_EOL;
            if (!$this->confirm('继续添加字段?',true)) {
                break;
            }
            $index++;
        } while (true);
        return $schemaData;
    }

    /**
     * 处理字段默认值
     * @param $fieldType
     * @return int|string|null
     */
    private function getDefault($fieldType): int|string|null
    {
        if (in_array($fieldType,["integer", "decimal","tinyInteger"])) {
            return '0';
        }elseif($fieldType=='string'){
            return '""';
        }
        return 'null';
    }




}
