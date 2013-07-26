This is what I expect from the various data storage components

Manca il caso del tag <g> in xliff

OK Original Content:	This is an example with "'!”£$%&/()=’?^+èéçò°§ù€<>
OK XLIFF <source>	:	This is an example with "'!”£$%&amp;/()=’?^+èéçò°§ù€&lt;&gt; (xmlentitity(Original))
OK Database (XLIFF):	This is an example with "'!”£$%&amp;/()=’?^+èéçò°§ù€&lt;&gt; (= XLIFF)
NO data-original   :	This is an example with &quot;'!”£$%&amp;/()=’?^+èéçò°§ù€&lt;&gt; ("=>&quot;)
NO source view		:	This is an example with "'!”£$%&amp;/()=’?^+èéçò°§ù€&lt;&gt; (= Database)
NO HTTP API Reques	:   This is an example with "'!”£%24%25%26%2F()%3D’%3F%5E%2Bèéçò°§ù€<> (quot;=>" -> htmldecoded->urlencoded (received as Orig)
NO HTTP API Respon :	This is an example with "'!”£%24%25%26%2F()%3D’%3F%5E%2Bèéçò°§ù€<> (received as Original)
