SELECT f.source FROM cms.full_text f JOIN cms.nodes n ON n.node = f.node WHERE n.uid = :uid AND f.locale = 'en';
