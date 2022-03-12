<?php

class AC0_JDImage extends JDApiBase
{
	protected function api_compose() {
		$ret = $this->compose($_REQUEST);
		return [
			"path" => $ret
		];
	}

	// uniqName("outdir", "202202", ".jpg") => "202202.jpg" / "202202-1.jpg"
	static function uniqName($dir, $prefix, $postfix) {
		$idx = 0;
		do {
			$f = $prefix . ($idx>0? "_$idx": "") . $postfix;
			$f1 = $dir . '/' . $f;
			++ $idx;
		}
		while (is_file($f1));
		touch($f1);
		return $f;
	}

	static function myQ($s, $forceQuote=false) {
		$s = preg_replace('/([\'"])/', '\\\\$1', $s);
		if ($forceQuote || preg_match('/[^\w_,.#]/', $s))
			return '"' . $s . '"';
		return $s;
	}
	private function createCmd($tplPage, $param, $outFile) {
		// 生成命令行
		$cmd = "magick -gravity northwest \\\n";
		$isEmpty = true;
		foreach ($tplPage["list"] as $e) {
			if (isset($e["name"]) && isset($param[$e["name"]])) {
				$e["value"] = $param[$e["name"]];
			}
			if (! $e["value"])
				continue;

			$arr = [];
			if ($e["type"] == "image") {
				$arr[] = $e["value"];
				// checkParams($e, ["pos"], "模板错误");
				if (isset($e["pos"])) {
					list ($x, $y) = explode(',', $e["pos"]);
					if (isset($e["size"])) {
						list ($w, $h) = explode(',', $e["size"]);
						$arr[] = "-geometry {$w}x{$h}+$x+$y";
					}
					else {
						$arr[] = "-geometry +$x+$y";
					}
					$arr[] = "-composite";
				}
			}
			else if ($e["type"] == "text") {
				checkParams($e, ["pos", "font", "fill", "size"], "模板错误");
				$drawCmd = [];
				foreach ([
					"font" => "font",
					"fill" => "fill",
					"size" => "font-size",
					"stroke" => "stroke",
					"stroke-width" => "stroke-width",
					"decorate" => "decorate",
					"pos" => "text"
				] as $k0 => $k) {
					if (isset($e[$k0])) {
						$drawCmd[] = $k . ' ' . self::myQ($e[$k0]);
					}
				}
				$s = join(' ', $drawCmd);
				$v = self::myQ($e["value"], true);
				$arr[] = "-draw '$s $v'";
# 				$arr[] = "-font \"" . $e["font"] . "\"";
# 				$arr[] = "-fill \"" . $e["fill"] . "\"";
# 				$arr[] = "-pointsize " . $e["size"];
#				$arr[] = "-draw 'text {$e["pos"]} \"{$e["value"]}\"'";
			}
			else if ($e["type"] == "param") {
				$arr[] = $e["value"];
			}
			if (count($arr) > 0) {
				$cmd .= join(' ', $arr) . " \\\n";
				$isEmpty = false;
			}
		}
		if ($isEmpty)
			jdRet(E_PARAM, "no work", "没有数据，无须合成");

		$cmd .= "$outFile\n";
		return $cmd;
	}

	// 固定生成命令文件: 模板目录/1.sh，输出到out.jpg，不支持并发
	// 返回生成的文件，支持一次多图: [ {path} ]
	function compose($param) {
		$tpl = mparam("template", $param);
		$tplDir = "upload/jdimage/" . $tpl;
		$tplFile = $tplDir . "/index.json";
		if (! is_file($tplFile))
			jdRet(E_PARAM, "bad tpl file: $tplFile", "找不到模板：`$tpl`");
		$tplContent = @jsonDecode(file_get_contents($tplFile));
		if (!isset($tplContent[0]["list"]))
			jdRet(E_PARAM, "bad tpl file: $tplFile", "模板格式错误：`$tpl`");

		$outDir = "out/" . date("Ym");
		$outDir1 = "upload/jdimage/" . $outDir;
		if (!is_dir($outDir1))
			mkdir($outDir1, 0770, true);

		// outFile不含目录名
		$pageCnt = count($tplContent);
		$outFile = self::uniqName($outDir1, date("Ymd_His") . '_' . $tpl . ($pageCnt>1? "-1": ""), ".jpg");

		$cmdArr = [];
		$resArr = [];
		$page = 1;
		foreach ($tplContent as $tplPage) {
			if ($pageCnt > 1 && $page > 1) {
				# "xx-1.jpg" => "xx-2.jpg"
				$outFile = str_replace("-" . ($page-1) . ".jpg", "-" . $page . ".jpg", $outFile);
			}
			$cmd1 = $this->createCmd($tplPage, $param, "../$outDir/$outFile");
			$cmdArr[] = $cmd1;
			$resArr[] = "$outDir1/$outFile";
			++ $page;
		}

		$cmd = "#!/bin/sh\ncd \"$tplDir\"\n" . join("\n", $cmdArr);
		logit($cmd, true, "debug");
		file_put_contents("1.sh", $cmd);
		exec("sh ./1.sh 2>&1", $out, $rv);
		if ($rv) {
			$outStr = join("\n", $out);
			logit("JDImage.compose fails: $cmd\nrv=$rv, out=$outStr");
			jdRet(E_SERVER, $outStr, "图像合成失败");
		}
		return $resArr;
	}
}

class AC2_JDImage extends AC0_JDImage
{
}
