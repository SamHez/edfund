<?php
declare(strict_types=1);
namespace EdFund;
/** Allowlisted Quill Delta document, not submitted HTML. Rendering escapes every text run. */
final class RichText
{
    public static function validate(array $delta): array
    {
        if(array_keys($delta)!==['ops'] || !is_array($delta['ops']) || !array_is_list($delta['ops']) || count($delta['ops'])<1 || count($delta['ops'])>2000){throw new \InvalidArgumentException('Invalid formatted text document.');}$total=0;$ends='';
        foreach($delta['ops'] as $op){
            if(!is_array($op) || array_diff(array_keys($op),['insert','attributes']) || !is_string($op['insert'] ?? null) || !mb_check_encoding($op['insert'],'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$op['insert'])){throw new \InvalidArgumentException('Text accepts paragraphs and basic formatting only.');}
            $total+=mb_strlen($op['insert']);$ends=$op['insert'];$attrs=$op['attributes'] ?? [];
            if(!is_array($attrs) || array_diff(array_keys($attrs),['bold','italic','link','header','list'])){throw new \InvalidArgumentException('Unsupported text format.');}
            foreach($attrs as $key=>$value){
                if(in_array($key,['bold','italic'],true) && $value!==true){throw new \InvalidArgumentException('Invalid emphasis.');}
                if($key==='link' && (!is_string($value) || strlen($value)>1000 || !Content::url($value))){throw new \InvalidArgumentException('Invalid text link.');}
                if($key==='header' && !in_array($value,[2,3],true)){throw new \InvalidArgumentException('Use H2 or H3 headings.');}
                if($key==='list' && !in_array($value,['ordered','bullet'],true)){throw new \InvalidArgumentException('Use numbered or bullet lists.');}
            }
            if((isset($attrs['header']) || isset($attrs['list'])) && (!preg_match('/\A\n+\z/',$op['insert']) || isset($attrs['header'],$attrs['list']))){throw new \InvalidArgumentException('Invalid paragraph formatting.');}
        }
        if($total>100000 || !str_ends_with($ends,"\n")){throw new \InvalidArgumentException('Text is too long or missing its paragraph ending.');}return $delta;
    }
    public static function html(array $delta): string
    {
        self::validate($delta);$html='';$line='';$list=null;
        foreach($delta['ops'] as $op){$attrs=$op['attributes'] ?? [];$pieces=explode("\n",$op['insert']);
            foreach($pieces as $i=>$piece){$text=htmlspecialchars($piece,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
                if($attrs['bold'] ?? false){$text='<strong>'.$text.'</strong>';}if($attrs['italic'] ?? false){$text='<em>'.$text.'</em>';}if(isset($attrs['link'])){$text='<a href="'.htmlspecialchars($attrs['link'],ENT_QUOTES,'UTF-8').'" rel="noopener noreferrer">'.$text.'</a>';}$line.=$text;
                if($i<count($pieces)-1){$next=isset($attrs['list'])?($attrs['list']==='ordered'?'ol':'ul'):null;
                    if($list!==$next){if($list){$html.='</'.$list.'>';}if($next){$html.='<'.$next.'>';}$list=$next;}
                    $tag=$list?'li':(isset($attrs['header'])?'h'.$attrs['header']:'p');$html.='<'.$tag.'>'.($line!==''?$line:'<br>').'</'.$tag.'>';$line='';
                }
            }
        }if($list){$html.='</'.$list.'>';}return $html;
    }
}
