<?php

namespace SimpleSchema;

class TableDefinition {
    protected $lines = [];

    static function autodetect($string) {
        if (is_string($string)) {
            if (stripos($string, 'CREATE TABLE')) {
                $obj = new static;
                $obj->setSql($string);
            } else {
                $obj = new static;
                $obj->setLines(explode("\n", $string));
            }
            return $obj;
        } else if (is_array($string)) {
            $obj = new static;
            $obj->setLines($string);
        } else if (is_a($string, static::class)) {
            return $string;
        }
    }
    /**
     * Convert a `CREATE TABLE` statement to table-format lines.
     */
    function setSql($createSql) {

        // dd($createSql);

        $lines = explode("\n", $createSql);

        $header = array_shift($lines);
        $tableSettings = array_pop($lines);
        $body = join("\n", $lines);

        $tableComment = get_preg_match($tableSettings, '~(?<comment>COMMENT=.+)~');

        if ($tableComment['comment'] ?? false) {
            parse_str($tableComment['comment'], $commentData);
            $body .= "\n # " . join("\n# ", explode("\n", json_decode('"'.substr($commentData['COMMENT'],1,-1).'"'))) . "\n";
        }
        
        $lines = explode("\n", trim($body));


        return $this->setLines($lines);
    }

    function setLines($lines) {
        $lines = array_map(function ($x) { return trim($x, ", \t\n"); }, $lines);
        $lines = array_filter($lines, function ($l) {
            return $l > '';
        });
        $this->lines = $lines;        
    }

    function getLines() {
        return $this->lines;
    }

    function parse() {
        $parsed = [];
        $position = "FIRST";
        foreach ($this->lines as $item) {
            $parsedItem = $this->parseLine($item);

            if ($parsedItem['class'] == 'field') {
                $parsedItem['position'] = $position;
                // Set next position
                // Only set position when it's a field.
                $position = "AFTER `{$parsedItem['field']}`";
            } else if ($parsedItem['@id'] === 'table_comment') {
                if (isset($parsed[$parsedItem['@id']])) {
                    $parsed[$parsedItem['@id']]['comment'] = toa($parsed[$parsedItem['@id']]['comment']);
                    $parsed[$parsedItem['@id']]['comment'][] = $parsedItem['comment'];
                    $parsed[$parsedItem['@id']]['full'] .= ' ' . $parsedItem['full'];
                } else {
                    $parsed[$parsedItem['@id']] = $parsedItem;
                }
                continue;
            }

            $parsed[$parsedItem['@id']] = $parsedItem;
        }
        return $parsed;
    }

    function parseLine($line) {

        if ($key = get_preg_match($line, '~(CONSTRAINT|PRIMARY KEY|UNIQUE KEY|FULLTEXT KEY|KEY)\s(`(.+?)`)*(.+)~')) {
            list(, $key_type, , $id) = $key;
            return [
                '@id' => 'key:'.$key_type.':'.$id,
                'class' => 'key',
                'key_type' => $key_type,
                'id' => $id,
                'full' => $line
            ];
        } elseif ($comment = get_preg_match($line, '~^\s*COMMENT\s(.+)~')) { 
            return [
                '@id' => 'table_comment',
                'class' => 'table_comment',
                'comment' => substr(trim($comment[1]), 1,-1),
                'full' => $line
            ];
        } elseif ($comment = get_preg_match($line, '~^\s*(#|\/\/|--)\s*(.+)~')) {
            return [
                '@id' => 'table_comment',
                'class' => 'table_comment',
                'comment' => trim($comment[2]),
                'full' => $line
            ]; 
        } else {
            list(, $field, $rest) = get_preg_match($line, '~`*(.+?)`*\s(.+)*~i');
            list($rest, $linecomment) = array_pad(preg_split('~(#|\/\/|--)\s*~', $rest),2, '');
            
            if ($linecomment) {
                $rest = "$rest COMMENT '" . addcslashes($linecomment, "'") . "'";
                $line = "`$field` $rest";
            } else {
                // assume comment is always last... 
                list($rest, $linecomment) = array_pad(preg_split('~COMMENT\s*~', $rest),2, '');
                if ($linecomment) {
                    $linecomment = substr(trim($linecomment), 1, -1);
                    // rest and line hoeven niet aangepast te worden.
                }
            }

            return [
                '@id' => 'field:'.$field,
                'class' => 'field',
                'field' => $field, 
                'type' => $rest,
                'comment' => $linecomment,
                'full' => $line,
            ];
        }
    }

    function compare($lines) {
        $oldFields = $this->parse();
        $newObject = static::autodetect($lines);
        $newFields = $newObject->parse();

        $sharedFields = array_intersect_key($newFields, $oldFields);
        
        $removedFields = array_diff_key($oldFields, $sharedFields);
        $addedFields = array_diff_key($newFields, $sharedFields);

        $modifiedFields = array_filter($sharedFields, function($field) use ($oldFields) {
            $fieldPos = $field['position'] ?? '';
            $oldPos = $oldFields[$field['@id']]['position'] ?? '';

            return !($field['full'] === $oldFields[$field['@id']]['full'] && $fieldPos === $oldPos);
        });

        foreach ($addedFields as $ax => $a) {
            if ($rename = get_preg_match($a['comment'] ?? '', '~originally:\s*\`*(\w+)\`*~')) {
                $renamed_field = $rename[1];
                if (isset($removedFields['field:'.$renamed_field])) {
                    $oldFields[$ax] = $oldFields['field:' . $renamed_field];
                    unset($addedFields[$ax]);
                    unset($removedFields['field:'.$renamed_field]);
                    $modifiedFields[$ax] = $a;
                }
            }
        }
        
        return [
            'old' => $oldFields,
            'new' => $newFields,
            'shared' => $sharedFields,
            'removed' => $removedFields,
            'added' => $addedFields,
            'modified' => $modifiedFields
        ];
    }

    function getSqlDiff($lines) {
        $mutations = $this->compare($lines);

        $lines = [];
        foreach ($mutations['added'] as $a_field => $a) {
            $fn = $a['class'] . '_add';
            $lines = array_merge($lines, toa($this->$fn($a)));
        }

        foreach ($mutations['modified'] as $s_field => $s) {
            $fn = $s['class'] . '_modify';
            $lines = array_merge($lines, toa($this->$fn($s, $mutations['old'][$s_field])));
        }

        foreach ($mutations['removed'] as $r_field => $r) {
            $fn = $r['class'] . '_remove';
            $lines = array_merge($lines, toa($this->$fn($r)));
        }

        return $lines;

    }

    function field_add($new) {
        return "ADD COLUMN {$new['full']} {$new['position']}";
    }

    function field_remove($new) {
        return "DROP COLUMN {$new['field']}";
    }

    function field_modify($new, $old) {
        if (isset($old['field']) && $new['field'] !== $old['field']) {
            return "CHANGE COLUMN `{$old['field']}` {$new['full']} {$new['position']}";
        }
        return "MODIFY COLUMN {$new['full']} {$new['position']}";
    }

    function key_add($new) {
        return "ADD {$new['full']}";
    }

    function key_remove($new) {
        switch($new['key_type']) {
            case 'PRIMARY KEY': return "DROP PRIMARY KEY";
            case 'CONSTRAINT': return "DROP CONSTRAINT `{$new['id']}`";
            default: return "DROP KEY `{$new['id']}`";
        }
    }

    function key_modify($new, $old) {
        // dd($new);
        return [$this->key_remove($new), $this->key_add($new)];
    }

    function table_comment_add($new) {
        return "COMMENT = '" . substr(json_encode(join("\n", toa($new['comment']))),1,-1) . "'";
    }

    function table_comment_modify($new) {
        return $this->table_comment_add($new);
    }
    
    function table_comment_remove() {
        return "COMMENT = ''";
    }


}