# -*- coding: utf-8 -*-

from odoo import models, fields, exceptions


class ssw_proc_start_wizard(models.TransientModel):
    _name = 'ssw.proc.start.wizard'
    _description = 'Start Procedure Wizard'

    template_id = fields.Many2one('ssw.proc.templates', string='Template', required=True)
    title = fields.Char(string='Title', required=True)
    description = fields.Text(string='Description', required=True)

    def action_confirm(self):
        self.ensure_one()
        if not self.title or not self.title.strip():
            raise exceptions.ValidationError("Please provide a title for this procedure.")
        if not self.description or not self.description.strip():
            raise exceptions.ValidationError("Please provide a description for this procedure.")
        return self.template_id._start_procedure_with_values(
            title=self.title.strip(),
            description=self.description.strip(),
        )
