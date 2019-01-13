<?php

declare(strict_types = 1);
/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          BufferHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */
/**
 * Trait welcher Objekt-Eigenschaften in den Instance-Buffer schreiben und lesen kann.
 */
trait BufferHelper
{
    /**
     * Wert einer Eigenschaft aus den InstanceBuffer lesen.
     *
     * @access public
     * @param string $name Propertyname
     * @return mixed Value of Name
     */
    public function __get($name)
    {
        if (strpos($name, 'Multi_') === 0) {
            $Lines = "";
            foreach ($this->{"BufferListe_" . $name} as $BufferIndex) {
                $Lines .= $this->{'Part_' . $name . $BufferIndex};
            }
            return unserialize($Lines);
        }
        return unserialize($this->GetBuffer($name));
    }

    /**
     * Wert einer Eigenschaft in den InstanceBuffer schreiben.
     *
     * @access public
     * @param string $name Propertyname
     * @param mixed Value of Name
     */
    public function __set($name, $value)
    {
        $Data = serialize($value);
        if (strpos($name, 'Multi_') === 0) {
            $OldBuffers = $this->{"BufferListe_" . $name};
            if ($OldBuffers == false) {
                $OldBuffers = array();
            }
            $Lines = str_split($Data, 8000);
            foreach ($Lines as $BufferIndex => $BufferLine) {
                $this->{'Part_' . $name . $BufferIndex} = $BufferLine;
            }
            $NewBuffers = array_keys($Lines);
            $this->{"BufferListe_" . $name} = $NewBuffers;
            $DelBuffers = array_diff_key($OldBuffers, $NewBuffers);
            foreach ($DelBuffers as $DelBuffer) {
                $this->{'Part_' . $name . $DelBuffer} = "";
            }
            return;
        }
        $this->SetBuffer($name, $Data);
    }
}
