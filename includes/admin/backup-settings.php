<?php

$slug = PYMTC_CHIP_FSLUG;

CHIP_FF_Settings::createSection( $slug, array(
  'id'          => 'backup-restore',
  'title'       => __( 'Backup and Restore', 'chip-for-paymattic' ),
  'icon'        => 'fa fa-copy',
  'description' => __( 'Backup and Restore your configuration.', 'chip-for-paymattic' ),
  'fields'      => array(
    array(
      'id'   => 'backup',
      'type' => 'backup',
    ),
  )
) );