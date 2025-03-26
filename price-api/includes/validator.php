<?php
/**
 * Classe per la validazione dei dati
 */
class Validator {
    /**
     * Valida un ASIN Amazon
     * 
     * @param string $asin ASIN da validare
     * @return bool True se valido, false altrimenti
     */
    public function validateAsin($asin) {
        return preg_match('/^[A-Z0-9]{10}$/', $asin);
    }
    
    /**
     * Valida un codice paese
     * 
     * @param string $country Codice paese da validare
     * @return bool True se valido, false altrimenti
     */
    public function validateCountry($country) {
        $supportedCountries = ['it', 'de', 'fr', 'es', 'uk'];
        return in_array(strtolower($country), $supportedCountries);
    }
    
    /**
     * Valida un prezzo
     * 
     * @param mixed $price Prezzo da validare
     * @return bool True se valido, false altrimenti
     */
    public function validatePrice($price) {
        if (!is_numeric($price)) {
            return false;
        }
        
        $price = (float)$price;
        return $price > 0 && $price < 10000; // Prezzo ragionevole
    }
    
    /**
     * Valida la fonte del prezzo
     * 
     * @param string $source Fonte da validare
     * @return bool True se valida, false altrimenti
     */
    public function validateSource($source) {
        $allowedSources = ['amazon', 'keepa', 'community', 'both', 'test'];
        return in_array($source, $allowedSources);
    }
}