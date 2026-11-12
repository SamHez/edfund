<?php
declare(strict_types=1);
namespace EdFund;

/** Editor/public destinations come from existing mappings, never a return URL. */
final class EditingContext
{
    public static function pageRoutes(\PDO $db): array
    {
        $routes=[];foreach((new Pages($db))->rows() as $page){$routes[$page['document_slug']]=$page['route'];}return $routes;
    }
    public static function editor(\PDO $db,array $config,string $path): ?array
    {
        if($path==='/admin/pages'){
            if(!isset($_GET['document'])){return null;}$slug=is_string($_GET['document'])?$_GET['document']:'home';$routes=self::pageRoutes($db);
            if(!isset($routes[$slug])){return null;}
            return ['url'=>$routes[$slug],'label'=>'Return to page','kind'=>'page','routes'=>$routes];
        }
        if($path==='/admin/post'){
            $id=filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id){return null;}
            try{$post=(new Posts($db,$config))->row($id);}catch(\InvalidArgumentException){return null;}
            $published=$post['published_id'] && !$post['trashed_at'];
            return ['url'=>$published?'/stories-updates/'.$post['published_slug'].'/':'/stories-updates/','label'=>$published?'Return to story':'View stories','kind'=>'story','routes'=>[]];
        }
        return null;
    }
}
