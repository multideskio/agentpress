<?php
/**
 * Script para gerar arquivo .mo a partir do .po
 * Rode: php generate-mo.php
 * 
 * Alternativa ao msgfmt para quem não tem gettext instalado.
 */

function po_to_mo( string $po_file, string $mo_file ): bool {
    $entries = [];
    $content = file_get_contents( $po_file );
    
    // Parse .po entries
    preg_match_all( '/msgid\s+"(.+?)"\s*\nmsgstr\s+"(.+?)"/s', $content, $matches, PREG_SET_ORDER );
    
    foreach ( $matches as $match ) {
        $msgid  = stripcslashes( $match[1] );
        $msgstr = stripcslashes( $match[2] );
        if ( $msgid !== '' && $msgstr !== '' ) {
            $entries[ $msgid ] = $msgstr;
        }
    }
    
    if ( empty( $entries ) ) {
        echo "Nenhuma entrada encontrada no .po\n";
        return false;
    }
    
    // Sort by msgid
    ksort( $entries );
    
    $ids    = array_keys( $entries );
    $strs   = array_values( $entries );
    $count  = count( $entries );
    
    // Build .mo binary
    $offsets_ids = [];
    $offsets_strs = [];
    
    // Calculate offsets
    $ids_block  = '';
    $strs_block = '';
    
    foreach ( $ids as $id ) {
        $offsets_ids[] = [ strlen( $id ), strlen( $ids_block ) ];
        $ids_block .= $id . "\0";
    }
    
    foreach ( $strs as $str ) {
        $offsets_strs[] = [ strlen( $str ), strlen( $strs_block ) ];
        $strs_block .= $str . "\0";
    }
    
    // Header size: magic(4) + revision(4) + count(4) + offset_orig(4) + offset_trans(4) + hash_size(4) + hash_offset(4) = 28 bytes
    $header_size   = 28;
    $table_size    = $count * 8; // Each entry: length(4) + offset(4)
    $ids_offset    = $header_size + $table_size * 2;
    $strs_offset   = $ids_offset + strlen( $ids_block );
    
    // Write .mo
    $mo = '';
    
    // Magic number
    $mo .= pack( 'V', 0x950412de );
    // Revision
    $mo .= pack( 'V', 0 );
    // Number of strings
    $mo .= pack( 'V', $count );
    // Offset of original strings table
    $mo .= pack( 'V', $header_size );
    // Offset of translated strings table
    $mo .= pack( 'V', $header_size + $table_size );
    // Hash table size
    $mo .= pack( 'V', 0 );
    // Hash table offset
    $mo .= pack( 'V', 0 );
    
    // Original strings table
    foreach ( $offsets_ids as $offset ) {
        $mo .= pack( 'V', $offset[0] ); // length
        $mo .= pack( 'V', $ids_offset + $offset[1] ); // offset
    }
    
    // Translated strings table
    foreach ( $offsets_strs as $offset ) {
        $mo .= pack( 'V', $offset[0] ); // length
        $mo .= pack( 'V', $strs_offset + $offset[1] ); // offset
    }
    
    // String blocks
    $mo .= $ids_block;
    $mo .= $strs_block;
    
    file_put_contents( $mo_file, $mo );
    echo "Gerado: {$mo_file} ({$count} strings)\n";
    return true;
}

// Run
$dir = __DIR__;
po_to_mo( $dir . '/agentpress-pt_BR.po', $dir . '/agentpress-pt_BR.mo' );
