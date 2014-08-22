<?php

include_once './ImageController.class.php';

if (!isset ($_GET ['img'])) throw new Exception ('No image is set');

ImageController::printImage ($_GET ['img']);