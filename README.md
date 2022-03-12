# jdcloud-plugin-image - 图像合成等处理

jdimage组件用于合成图像模板和参数数据，生成最终的图片。

	图像模板 + 参数数据 = 实例图片

## 安装

	./tool/jdcloud-plugin.sh add ../jdcloud-plugin-image

### 安装ImageMagick软件

由于插件通过image magick软件命令行完成图片处理，须安装image magick软件。

在Windows平台，请安装版本7以上(安装文件如ImageMagick-7.0.8-12-Q16-x64-dll.exe)。注意：版本7以前命令名为convert，版本7之后名为magick.
然后确保以下命令可用：

	sh
	magick

注意：Win10等系统中apache服务可能无法执行git-bash下的sh系列命令，表现为找不到命令或调用卡死（在进程管理器中可用sh进程且占用CPU很高）。
请确保：

- 在Windows环境下，sh是安装git-bash后自带的（路径示例：C:\Program Files\Git\usr\bin）；
	如果使用Apache系统服务的方式（默认是SYSTEM用户执行），应确保上述命令行在系统PATH（而不只是当前用户的PATH）中。

- Win10环境中Apache+php调用shell可能会卡死，应修改git-bash下的文件：/etc/nsswitch.conf （路径示例：C:\Program Files\Git\etc\nsswitch.conf）

		db_home: env 
		#db_home: env windows cygwin desc

如果仍无法解决，则通过在Windows服务管理器中将服务的登录用户（默认是SYSTEM）改为当前用户可以解决。

在CentOS7上，安装ImageMagick:

	sudo yum install ImageMagick

由于默认是6.9版本（命令名为convert），须做一下兼容，让magick命令可用

	sudo ln -sf /usr/bin/convert /usr/bin/magick

## 图像模板

插件自带一个示例模板，目录为 upload/jdimage/card

图像模板是一个目录，其中 index.json 文件定义了模板，示例如下：

	[
		{
			list: [
				{
					type: "image",
					value: "background.jpg"
				},
				{
					type: "text",
					pos: "200,50",
					name: "名字", // 参数名
					value: "张三", // 参数默认值，可以不指定
					fill: "#777777", // 颜色
					font: "FZLTHJW.TTF", // 字体
					size: 36, // 字号
				},
				{
					type: "text",
					pos: "200,100",
					name: "职位",
					value: "销售总监",
					fill: "#770000", // 颜色
					font: "FZLTHJW.TTF", // 字体
					size: 24, // 字号
				},
				{
					type: "image",
					pos: "200,150",
					size: "80,80", // 图片的宽和高
					name: "logo",
					value: "logo.png",
				}
			]
		}
	]

**注意**：其中引用的文件名，必须大小写与文件一致，否则Linux平台下找不到文件!

模板是一个数组，支持多页，每一项是一页。

说明：

- type: Enum(image-图，text-文, param-处理参数)
- name: 可选，如果指定，则表示它是个可变参数，name就是参数名，其值(value)可以被参数数据覆盖。
- value: 可选，在合成时，可被参数数据覆盖。如果value未指定，且参数中也未指定，则不处理该项。
	如果type=image，表示文件路径，一般用目录内的相对路私。
	如果type=text，则是文本内容。
- size: 用于type=text时，表示字号; 用于type=image时, 是可选的，指定图片长宽。

示例参数数据：

	{
		"名字": "李四",
		"职位": "销售总监",
		"logo": "logo.png"
	}

处理命令参考：

	magick -gravity northwest \
		background.jpg \
		-draw 'font FZLTHJW.TTF fill #777777 font-size 36 text 200,50 "李四"'  \
		-draw 'font FZLTHJW.TTF fill #770000 font-size 24 text 200,100 "销售总监"'  \
		logo.png  -geometry 80x80+200+150 -composite \
		out.jpg 

如果多页，则生成多个命令。

多页模板示例：

	[
		{
			name: "正面",
			list: {
				...
			}
		},
		{
			name: "反面",
			list: {
				...
			}
		}
	]

如果只有一页，生成图片名为xx.jpg，如果是多页，图片名后会带上后缀 xx-1.jpg, xx-2.jpg 这样。

### 关于粗体

使用粗体需要有专门对应的字体文件，如果没有，则一般通过描边（stroke/stroke-width）来模拟粗体，设置示例：

	{
		"type": "text",
		"pos": "200,50",
		"name": "名字", // 参数名
		"value": "张三", // 参数默认值，可以不指定
		"fill": "#777777", // 颜色
		"font": "FZLTHJW.TTF", // 字体
		"size": 36, // 字号

		// 模拟粗体：
		"stroke": "#777777", // 边缘颜色与字色一致
		"stroke-width": 1, // 边宽，常用1或2，字体越大，值可以越大。
	},

### 印刷需要CMYK图片

一般电脑上看到的图片的颜色空间(colorspace)是sRGB，而印刷需要颜色空间为CMYK的图片（注意与打印不同，打印一般也用sRGB），否则会有色差。

如果底图是sRGB，那么合成后为了印刷时颜色不产生色差，可以转换图片格式，

	// 某模板：
	{
		"list": [
			{
				type: "image",
				...
			},
			...,
			// 转为CMYK图片
			{
			  "type": "param",
			  "value": "-colorspace CMYK"
			}
		]
	}

其原理是在命令行的最后加上指定参数，如：

	magick ... -colorspace CMYK ...

注意：

sRGB图片转CMYK图片后，在chrome系的浏览器中肉眼看不出色差等区别；
但在IE、Windows图片编辑器(mspaint)等软件中可以看到色差。这可能是各软件在转换颜色空间时使用的profile（即sRGB与CMYK的对应关系）不同导致。

如果底图是CMYK图片，则无须做转换。

centos 7上imagemagick 6.x版本有个bug，如果底图是CMYK，则处理过程中叠加其它图片时颜色会反色。为应对这种情况，可先将底图转为sRGB，处理完后再转到CMYK，所以配置为：

	// 某模板：
	{
		"list": [
			{
				type: "image",
				...
			},
			// 先转为sRGB图片
			{
			  "type": "param",
			  "value": "-colorspace sRGB"
			},
			// 叠加其它sRGB图片
			{
			  "type": "image",
			  "pos": "265,982",
			  "size": "300,300",
			  "name": "二维码",
			  "value": ""
			},
			// 最终转为CMYK图片
			{
			  "type": "param",
			  "value": "-colorspace CMYK"
			}
		]
	}

### 使用命令行参数进行图片编辑

在处理时可以通过`type: "param"`来指定任意magick软件的命令行参数，参考文档：
http://www.imagemagick.org/script/command-line-options.php#draw

示例：转灰度图

	{
	  "type": "param",
	  "value": "-colorspace gray"
	}

示例：压缩图片使其最大宽高不超过1200：

	{
	  "type": "param",
	  "value": "-resize 1200x1200"
	}

上例中如果是小图片，则会被拉伸到1200，为避免这种情况可设置为：

	{
	  "type": "param",
	  "value": "-resize '1200x1200>'"
	}

## 接口

根据模板合成图片：

	JDImage.compose()(template, ...) -> {path}

- template: 指定图像模板，对应一个后端目录。如"card"对应`upload/jdimage/card`目录。
	模板事先制作好放置于目录中。

其它参数为图像模板中定义的参数数据。

生成图像到目录: upload/jdimage/out

返回：

- path: 文件相对路径。

示例：

	callSvr("JDImage.compose", {
		template: "card", // 对应模板目录 upload/jdimage/card
		"名字": "李四",
		"职位": "销售总监",
	})

返回示例：

	[
		{ path: "upload/202202/333.jpg" }
	]

如果有多张图，会加数字后缀：

	[
		{ path: "upload/202202/333-1.jpg" }
		{ path: "upload/202202/333-2.jpg" }
	]

通过拼接baseUrl可得到图片完整URL。

## 后端内部接口

	AC0_JDImage.compose(param)

示例：

	$img = new AC0_JDImage();
	$rv = $img->compose({
		template: "card",
		"名字": "李四",
		"职位": "销售总监",
	});
	// rv是个数组: [ { path: "xxx.jpg" } ]

