
	#################################################
	################  COMPLEMENTO BLING #############
	#################################################

	public $produto;


	
	public function upImagem($urlimagem){
		$ch = curl_init($urlimagem);
		$imagem = 'catalog/produtos/'.md5(rand(0,100000)).".jpg";
		$fp = fopen(DIR_IMAGE.$imagem, 'wb');

		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		return $imagem;
	}


	public function blingCURL($url,$parametros = array("imagem"=>"S")){
		$params = array(
			"apikey"=>" DIGITE SUA APIKEY AQUI"
		);

		$params = $params + $parametros;
		
		
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL, $url . '?' . http_build_query($params));
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($curl_handle);
		curl_close($curl_handle);
		return json_decode($response,true);
	}

	public function blingProdutos(){
		$produto = $this->blingCURL("https://bling.com.br/Api/v2/produtos/json/");
		return $produto["retorno"]["produtos"];
	}

	public function blingProduto($cod){
		$produto = $this->blingCURL("https://bling.com.br/Api/v2/produto/{$cod}/json/");
		return $produto["retorno"]["produtos"];
	}

	public function criarlog($texto){
		$arquivo = fopen(DIR_IMAGE."catalog/produtos/meuarquivo.txt",'a+');
		fwrite($arquivo, $texto."\n");
		fclose($arquivo);

	}

	public function add_imagens($idproduto,$imagens){
		$imgs = array();
		foreach($imagens as $img){
			$imagemUp = $this->upImagem($img['link']);
			$imgs[] = "(NULL, '".$idproduto."', '".$imagemUp."', '0')";
		}
		
		$q = "INSERT INTO " . DB_PREFIX . "product_image (`product_image_id`, `product_id`, `image`, `sort_order`) VALUES ".implode(',',$imgs);
		//$this->criarlog($q);
		$sql = $this->db->query($q);
	}

	public function grava_produto($parameter){
		if($parameter->estoqueAtual <= 0){
			$estoque_status = 0;
		}else{
			$estoque_status = 1;
		}

		if(!empty($this->produto['imagem'][0]['link'])){
			$imagem = $this->upImagem($this->produto['imagem'][0]['link']);
		}else{
			$imagem = '';
		}

		$sql = $this->db->query("INSERT INTO " . DB_PREFIX . "product (model, sku, upc, ean, jan, isbn, mpn, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, points, tax_class_id, date_available, weight, weight_class_id, length, width, height, length_class_id, subtract, minimum, sort_order, status, viewed, date_added, date_modified ) 
		VALUES ('', '".strip_tags($parameter->codigo)."','','".$this->produto['gtin']."','','','','','".$parameter->estoqueAtual."','".$estoque_status."','".$imagem."','0','1','".$parameter->preco."','0', '9','".date('Y-m-d')."', '".$parameter->peso."',	'1','".$parameter->profundidadeProduto."','".$parameter->larguraProduto."','".$parameter->alturaProduto."','1','0','1','0','1','0', NOW(), NOW())");

		$query = $this->db->query("SELECT model, sku, quantity, MAX(product_id) as maximo FROM " . DB_PREFIX . "product");
		$store = $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store (product_id, store_id) VALUES ('".$query->row['maximo']."','0')");

		if(count($this->produto['imagem']) > 0 ){
			array_shift($this->produto['imagem']);
			$imagens = $this->produto['imagem'];
			$this->add_imagens($query->row['maximo'],$imagens);

		}
		return $query->rows;
	}

	

	//Plugin
	public function getAllProduct(){
		$query = $this->db->query("SELECT pd.product_id, pd.name, pd.description, p.model, p.sku, p.quantity, p.price, p.weight, p.length, p.width, p.height,p.date_added, pa.text AS attribute 					
								   FROM " . DB_PREFIX . "product p LEFT JOIN  " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id LEFT JOIN  " . DB_PREFIX . "product_attribute pa ON  pa.product_id = p.product_id 							   
								   GROUP BY p.product_id"
							      );
		return $query->rows;
	}
	
	//Products by filters
	public function getAllProductFilters($filters){
		$filters = urldecode($filters);
		$filter = explode('|', $filters);
		$startDate = $filter[0];
		$finishDate = $filter[1];
		
		if($startDate == date('Y-m-d')){
			$d = date('d') +1;
			$y = date('Y');
			$m = date('m'); 
			$finishDate = $y."-".$m ."-".$d;
		}

		$query = $this->db->query("SELECT pd.product_id, pd.name, pd.description, p.model, p.sku, p.quantity, p.price, p.weight, p.length, p.width, p.height,p.date_added, pa.text AS attribute 					
								   FROM " . DB_PREFIX . "product p LEFT JOIN  " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id LEFT JOIN  " . DB_PREFIX . "product_attribute pa ON  pa.product_id = p.product_id 							   
								   WHERE p.date_added BETWEEN '".$startDate."' AND '".$finishDate."'  AND p.status = '1'   							  
								   GROUP BY p.product_id"
		);
		return $query->rows;
	}
	
	
	//Products by filters
	public function getCountProduct(){
		$query = $this->db->query("SELECT COUNT(product_id) as NrProducts FROM " . DB_PREFIX . "product ");
		return $query->rows;
	}


	//Insert products
	public function insert_oc_products($parameter){
		if(strlen($parameter->descricaoComplementar) > 64){
			//$parameter->descricaoComplementar = substr($parameter->descricaoComplementar, 0, 64);
		}

		$produto = $this->blingProduto(strip_tags($parameter->codigo));
		$this->produto = $produto[0]["produto"];
					
		$idProd = (int)$parameter->id;
		if($idProd == 0){
			return $this->grava_produto($parameter);
		}else{
			$query = $this->db->query("SELECT product_id WHERE product_id='" .$idProd. "' FROM " . DB_PREFIX . "product");

			if($query->rows > 0){
				$sql = $this->db->query("UPDATE " . DB_PREFIX . "product SET sku = '".$parameter->codigo."', quantity = '".$parameter->estoqueAtual."',image = '".$this->produto["imagem"][0]['link']."', price = '".$parameter->preco."', weight = '".$parameter->peso."', shipping = '1', height = '".$parameter->alturaProduto."', width = '".$parameter->larguraProduto."', height = '".$parameter->alturaProduto."', date_modified = NOW()  WHERE product_id = '" . $idProd."'");
				return array('id' => $idProd, 'returnUp' => $sql);	
			}else{
				return $this->grava_produto($parameter);
			}		
		}
}
	
	public function update_oc_description($parameter, $id){
		$sql = $this->db->query("UPDATE " . DB_PREFIX . "product_description SET `name` =  '".strip_tags($parameter->nome)."',`meta_title` =  '".strip_tags($parameter->nome)."',  `description` = '".htmlentities($parameter->descricaoComplementar)."' WHERE `product_id` = '".$id."'");
		return $sql;
	}
	
	public function insert_oc_description($parameter, $id){
		$sql = $this->db->query("INSERT INTO " . DB_PREFIX . "product_description (`product_id`, `language_id`, `name`, `description`, `tag`, `meta_title`, `meta_description`, `meta_keyword`)
			   		 VALUES('".$id."','". (int)$this->config->get('config_language_id')."','".strip_tags($parameter->nome)."','".base64_decode($parameter->descricaoComplementar)."','','".strip_tags($parameter->nome)."','','')");
		
		$query = $this->db->query("SELECT MAX(product_id) as idMax FROM " . DB_PREFIX . "product_description");
		return $query->rows;
	}

	public function delete_oc_products($id){
		$del = $this->db->query("DELETE FROM `" . DB_PREFIX . "product` WHERE product_id = '".$id."'");
		return true;
	}

	//Get products variations
	public function getVariation($parameters){

		$query = $this->db->query("SELECT pd.name as variationName, od.name as nomeTipoVariacao,  ovd.name as tipoVariacao, pov.quantity as quantidadeVariacao, pov.price as precoVaricao, pov.price_prefix as prefixPrecoVaricao,  pov.weight as   pesoVaricao, pov.weight_prefix as prefixPesoVaricao, pov.product_option_value_id as idVariation
					   FROM " . DB_PREFIX . "option_description od 
					   LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON ( od.option_id = ovd.option_id )
					   LEFT JOIN " . DB_PREFIX . "product_option_value pov ON ( ovd.option_value_id = pov.option_value_id )
					   LEFT JOIN " . DB_PREFIX . "product_description pd ON ( pov.product_id = pd.product_id )
			   		   WHERE pd.product_id = '".$parameters['product_id']."'");
				return $query->rows;
	}
	
	public function update_stock_product($id, $qtd){
		$up = $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity`= '" . $qtd . "' WHERE  `product_id` = '" . $id . "'");
		if($up){
			return true;
		}else{
			return false;
		}	
	}
	
	public function update_stock_variation($id, $qtd){
		$up = $this->db->query("UPDATE `" . DB_PREFIX . "product_option_value` SET `quantity`= '" . $qtd . "' WHERE  `product_option_value_id` = '" . $id . "' ");
		if($up){
			return true;
		}else{
			return false;
		}		
	}

	public function update_price_product($id, $price){
		$up = $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `price`= '" . $price . "' WHERE  `product_id` = '" . $id . "'");
		if($up){
			return true;
		}else{
			return false;
		}	
	}
	
	public function update_price_variation($id, $price){
		$up = $this->db->query("UPDATE `" . DB_PREFIX . "product_option_value` SET `price`= '" . $price . "' WHERE  `product_option_value_id` = '" . $id . "' ");
		if($up){
			return true;
		}else{
			return false;
		}		
	}

}
